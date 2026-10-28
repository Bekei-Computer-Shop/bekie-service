<x-mail::layout>
	<x-slot:header>
		<tr>
			<td align="center" style="padding: 28px 16px 22px;">
				<table align="center" cellpadding="0" cellspacing="0" role="presentation" width="570" style="width: 100%; max-width: 570px; border-collapse: collapse;">
					<tr>
						<td bgcolor="#1F2933" style="background-color: #1F2933; border-radius: 6px 6px 0 0; padding: 0;">
							<table cellpadding="0" cellspacing="0" role="presentation" width="100%" style="border-collapse: collapse;">
								<tr>
									<td bgcolor="#F2B51B" height="5" style="background-color: #F2B51B; font-size: 0; line-height: 5px;">&nbsp;</td>
								</tr>
								<tr>
									<td align="center" style="padding: 21px 20px 22px; color: #FFFFFF; font-family: Arial, Helvetica, sans-serif; font-size: 22px; font-weight: bold; line-height: 28px;">
										{{ config('app.name') }}
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</x-slot:header>

	<table cellpadding="0" cellspacing="0" role="presentation" width="100%" style="border-collapse: collapse;">
		<tr>
			<td style="padding: 2px 0 8px;">
				<h1 style="margin: 0 0 14px; color: #17212B; font-family: Arial, Helvetica, sans-serif; font-size: 26px; font-weight: 700; line-height: 34px; text-align: center;">
					Verify your email address
				</h1>
				<p style="margin: 0 0 24px; color: #4B5563; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 25px; text-align: center;">
					You're almost ready to get started. Enter the verification code below to confirm your email address.
				</p>
			</td>
		</tr>
		<tr>
			<td style="padding: 0 0 23px;">
				<table cellpadding="0" cellspacing="0" role="presentation" width="100%" style="border-collapse: separate; border-spacing: 0;">
					<tr>
						<td align="center" bgcolor="#FFF9E8" style="background-color: #FFF9E8; border: 1px solid #F1D88A; border-radius: 5px; padding: 24px 12px 25px;">
							<p style="margin: 0 0 9px; color: #5B6470; font-family: Arial, Helvetica, sans-serif; font-size: 13px; font-weight: 600; line-height: 19px; text-align: center; text-transform: uppercase;">
								Your verification code
							</p>
							<p style="margin: 0; color: #17212B; font-family: Arial, Helvetica, sans-serif; font-size: 34px; font-weight: 700; letter-spacing: 7px; line-height: 44px; text-align: center;">
								{{ $otp }}
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td style="padding: 0 2px 18px;">
				<p style="margin: 0 0 11px; color: #4B5563; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 22px; text-align: center;">
					This verification code expires in {{ $expiresMinutes }} minutes and can only be used once.
				</p>
				<p style="margin: 0; color: #6B7280; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 22px; text-align: center;">
					If you didn't request this code, you can safely ignore this email.
				</p>
			</td>
		</tr>
	</table>

	<x-slot:footer>
		<tr>
			<td align="center" style="padding: 0 16px 22px;">
				<table align="center" cellpadding="0" cellspacing="0" role="presentation" width="570" style="width: 100%; max-width: 570px; border-collapse: collapse;">
					<tr>
						<td align="center" style="border-top: 1px solid #E5E7EB; padding: 19px 12px 0; color: #374151; font-family: Arial, Helvetica, sans-serif; font-size: 13px; font-weight: 600; line-height: 19px;">
							{{ config('app.name') }}
						</td>
					</tr>
					<tr>
						<td align="center" style="padding: 5px 12px 0; color: #8A929C; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px;">
							&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</x-slot:footer>
</x-mail::layout>
