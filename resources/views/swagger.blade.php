<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@4/swagger-ui.css" />
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@4/swagger-ui-bundle.js"></script>
<script src="https://unpkg.com/swagger-ui-dist@4/swagger-ui-standalone-preset.js"></script>
<script>
    window.onload = function() {
        if (typeof SwaggerUIBundle === 'undefined') {
            document.getElementById('swagger-ui').innerHTML = '<p style="color:red;">Swagger UI failed to load.</p>';
            return;
        }

        SwaggerUIBundle({
            url: '{{ url('/openapi.json') }}',
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset,
            ],
            layout: 'StandaloneLayout',
            docExpansion: 'none',
            deepLinking: true,
        });
    };
</script>
</body>
</html>
