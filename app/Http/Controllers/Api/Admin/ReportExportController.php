<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Admin\ExportReportRequest;
use App\Models\TeamActivityLog;
use App\Models\VisitorLog;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends BaseAdminController
{
    public function export(ExportReportRequest $request)
    {
        $validated = $request->validated();
        $from = $validated['date_from'];
        $to = $validated['date_to'];
        $granularity = $validated['granularity'] ?? 'daily';
        $format = $validated['format'];

        $data = [
            'orders' => [],
            'visitor_counts' => [],
            'team_events' => [],
        ];

        $data['orders'] = app(ReportExport::class)->prepareOrderSummary($from, $to, $granularity);
        $data['visitor_counts'] = VisitorLog::whereBetween('created_at', [$from, $to])->count();
        $data['team_events'] = TeamActivityLog::whereBetween('created_at', [$from, $to])->count();

        $filename = sprintf('admin-report_%s_%s_%s.%s', $from, $to, $granularity, $format);

        TeamActivityLog::record(auth()->user(), 'report_export', 'Exported admin report', [
            'from' => $from,
            'to' => $to,
            'granularity' => $granularity,
            'format' => $format,
        ]);

        if ($format === 'csv') {
            return Excel::download(new ReportExport($data), $filename, \Maatwebsite\Excel\Excel::CSV);
        }

        $pdf = Pdf::loadView('exports.report', ['data' => $data, 'meta' => $validated]);

        return $pdf->download($filename);
    }
}
