<?php

namespace PhalconOpenApi;

use Phalcon\Mvc\Controller;

class DocsController extends Controller
{
    private ?array $cachedSpec = null;

    public function specAction()
    {
        if ($this->cachedSpec === null) {
            /** @var SpecAssembler $generator */
            $generator = $this->di->getShared('openApiGenerator');
            $this->cachedSpec = $generator->generate();
        }

        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setJsonContent($this->cachedSpec);
        return $this->response;
    }

    public function docsAction()
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        SwaggerUIBundle({
            url: '/api/openapi.json',
            dom_id: '#swagger-ui',
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
            layout: 'BaseLayout'
        });
    </script>
</body>
</html>
HTML;

        $this->response->setContent($html);
        return $this->response;
    }
}
