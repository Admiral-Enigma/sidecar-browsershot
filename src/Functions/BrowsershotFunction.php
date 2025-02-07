<?php

namespace Wnx\SidecarBrowsershot\Functions;

use Hammerstone\Sidecar\LambdaFunction;
use Hammerstone\Sidecar\Package;
use Hammerstone\Sidecar\Runtime;
use Hammerstone\Sidecar\WarmingConfig;
use Illuminate\Support\Facades\Storage;

class BrowsershotFunction extends LambdaFunction
{
    public function handler()
    {
        return 'browsershot.handle';
    }

    public function name()
    {
        return 'browsershot';
    }

    public function package()
    {
        return Package::make()
            ->includeStrings([
                'browser.js' => $this->modifiedBrowserJs(),
            ])
            ->includeExactly([
                __DIR__ . '/../../resources/lambda/browsershot.js' => 'browsershot.js',
                __DIR__ . '/../../resources/lambda/NotoColorEmoji.ttf' => 'NotoColorEmoji.ttf',
            ]);
    }

    /**
     * We get puppeteer out of the layer, which spatie doesn't allow
     * for. We'll just overwrite their browser.js to add it.
     *
     * @return string
     */
    protected function modifiedBrowserJs()
    {
        if (app()->environment('testing')) {
            $browser = file_get_contents('vendor/spatie/browsershot/bin/browser.js');
        } else {
            $browser = file_get_contents(base_path('vendor/spatie/browsershot/bin/browser.js'));
        }

        // Remove their reference.
        $browser = str_replace('const puppet = (pup || require(\'puppeteer\'));', '', $browser);

        // Add ours.
        return "const puppet = require('@sparticuz/chrome-aws-lambda').puppeteer; \n" . $browser;
    }

    public function runtime()
    {
        return Runtime::NODEJS_14;
    }

    public function memory()
    {
        return config('sidecar-browsershot.memory');
    }

    public function warmingConfig()
    {
        return WarmingConfig::instances(config('sidecar-browsershot.warming'));
    }

    public function layers()
    {
        if ($layer = config('sidecar-browsershot.layer')) {
            return [$layer];
        }

        $region = config('sidecar.aws_region');

        // https://github.com/shelfio/chrome-aws-lambda-layer
        return ["arn:aws:lambda:{$region}:764866452798:layer:chrome-aws-lambda:31"];
    }

    public function beforeDeployment()
    {
        // Download NotoColorEmoji font to support rendering emojis in Browsershot.
        // A fresh font file is downloaded, when the font file is not present.
        if (! file_exists(__DIR__ . '/../../resources/lambda/NotoColorEmoji.ttf')) {
            $font = file_get_contents('https://raw.githubusercontent.com/googlefonts/noto-emoji/main/fonts/NotoColorEmoji.ttf');
            file_put_contents(__DIR__ . '/../../resources/lambda/NotoColorEmoji.ttf', $font);
        }
    }
}
