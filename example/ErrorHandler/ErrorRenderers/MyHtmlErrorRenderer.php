<?php

namespace Example\ErrorHandler\ErrorRenderers;

use Simsoft\Slim\ErrorRenderers\AbstractHtmlErrorRenderer;
use Throwable;

class MyHtmlErrorRenderer extends AbstractHtmlErrorRenderer
{
    public function renderHtmlBody(Throwable $exception): string
    {
        // write your own HTML body
        return strtr('<html lang="en">
                    <header>
                        <title>{{ code }} {{ title }}</title>
                    </header>
                    <body>
                        {{ message }} <br />
                        {{ description }}
                    </body>
                    </html>', [
            '{{ code }}' => $exception->getCode(),
            '{{ title }}' => $this->getErrorTitle($exception),
            '{{ message }}' => $exception->getMessage(),
            '{{ description }}' => $this->getErrorDescription($exception),
        ]);
    }
}