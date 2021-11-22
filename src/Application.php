<?php

declare(strict_types = 1);

namespace Armin\Md2Pdf;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Mpdf\Mpdf;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;

class Application extends SingleCommandApplication
{
    public function __construct(string $name = 'md2pdf')
    {
        parent::__construct($name);

        $this
            ->setName($name)
            ->setVersion(self::getApplicationVersionFromComposerJson())
            ->setCode([$this, 'executing'])
            ->addArgument('file', InputArgument::REQUIRED, 'File')
        ;
    }

    protected function executing(Input $input, Output $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // TODO Prototype
        $converter = new GithubFlavoredMarkdownConverter([
            // options
        ]);

        $contents = file_get_contents($input->getArgument('file'));
        $html = $converter->convertToHtml($contents);

        $mpdf = new Mpdf([
            'tempDir' => getcwd() . '/tmp',
        ]);
        $mpdf->WriteHTML($html);
        $base = basename($input->getArgument('file'));
        $mpdf->Output($base . '.pdf', \Mpdf\Output\Destination::FILE);

        $io->success('Created pdf file ' . $base . '.pdf');
        // TODO Prototype

        return 0;
    }

    public static function getApplicationVersionFromComposerJson(): string
    {
        $data = file_get_contents(__DIR__ . '/../composer.json');
        if (!$data) {
            // @codeCoverageIgnoreStart
            return '';
            // @codeCoverageIgnoreEnd
        }
        $json = json_decode($data, true);

        return $json['version'] ?? '';
    }
}
