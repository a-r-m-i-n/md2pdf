<?php

declare(strict_types = 1);

namespace Armin\Md2Pdf\Service;

use Armin\Md2Pdf\Configuration;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\Normalizer\SlugNormalizer;
use Mpdf\Mpdf;
use Symfony\Component\Console\Style\SymfonyStyle;

class Converter
{
    private Configuration $configuration;
    private string $workingDirectory;

    public function __construct(Configuration $configuration, string $workingDirectory)
    {
        $this->configuration = $configuration;
        $this->workingDirectory = $workingDirectory;
    }

    /**
     * @TODO Refactor me!
     */
    public function convert(SymfonyStyle $io): void
    {
        $rootPath = realpath($this->workingDirectory . DIRECTORY_SEPARATOR . $this->configuration['rootPath']);
        $io->writeln('<comment>Configured root path: ' . $rootPath . '</comment>', SymfonyStyle::VERBOSITY_VERBOSE);

        // Init Markdown Converter
        $markdownConverter = new GithubFlavoredMarkdownConverter([
            // TODO Make Markdown Converter options configurable
        ]);

        $mpdf = new Mpdf([
            // TODO Make MPDF options configurable
            'tempDir' => $rootPath . '/tmp',
        ]);
        $io->writeln('<comment>MPDF tempDir: ' . $mpdf->tempDir . '</comment>', SymfonyStyle::VERBOSITY_VERBOSE);

        // TODO: Make toc levels configurable
        $mpdf->h2toc = [
            'H1' => 0,
            'H2' => 1,
            'H3' => 2,
            'H4' => 3,
            'H5' => 4,
            'H6' => 5,
        ];
        $mpdf->AddPage('', '', '', '', 'on');
        $mpdf->WriteHTML('<style>' . $this->configuration['styles'] . '</style>');
        $mpdf->SetTitle($this->configuration['title']);

        $mpdf->WriteHTML('<div class="title">' . $this->configuration['title'] . '</div><div class="author">' . $this->configuration['author'] . '</div>');
        if ($this->configuration['enableToc']) {
            $io->writeln('<comment>Adding TOC with headline "' . $this->configuration['tocHeadline'] . '"</comment>', SymfonyStyle::VERBOSITY_VERBOSE);
            $mpdf->TOCpagebreak('', '', '', true, true, '', '', '', '', '30', '', '', '', '', '', '', '', '', '', '', '<h1>' . $this->configuration['tocHeadline'] . '</h1>', '', '', '1', '1', 'off');
        }

        if ($this->configuration['enableFooter']) {
            $mpdf->SetHTMLFooter(
                '<table class="footer" style="width: 100%;"><tr><td>' . $this->configuration['title'] . '</td><td style="text-align: right;">Page {PAGENO}</td></tr></table>'
            );
        }

        $createdAnchors = [];
        $convertedRelativeLinks = [];
        $htmlParts = [];
        $lastSectionLevel = 0;
        $io->writeln('Reading markdown files from configured structure...');
        $io->writeln('<comment>Found ' . count($this->configuration['structure']) . '" entries in document\'s structure.</comment>', SymfonyStyle::VERBOSITY_VERBOSE);

        $io->progressStart(count($this->configuration['structure']));
        foreach ($this->configuration['structure'] as $item) {
            if (is_array($item)) {
                if (array_key_exists('section', $item)) {
                    $lastSectionLevel = $item['level'] ?? 1;
                    $htmlParts[] = '<h' . $lastSectionLevel . '>' . $item['section'] . '</h' . $lastSectionLevel . '>';
                    if (isset($item['contents']) && !empty($item['contents'])) {
                        $htmlParts[] = '<div class="section-contents">' . $item['contents'] . '</div>';
                    }
                }
            } else {
                $slugNormalizer = new SlugNormalizer();
                $path = $rootPath . DIRECTORY_SEPARATOR . $item;
                if (file_exists($path)) {
                    // Convert Markdown to HTML
                    $html = (string)$markdownConverter->convertToHtml(file_get_contents($path) ?: '');

                    // Prepend file anchor
                    $normalizedText = $slugNormalizer->normalize($item);
                    $createdAnchors[$item] = $normalizedText;
                    $itemParts = explode('/', $item);
                    if (count($itemParts) > 1) {
                        // Add relative variations of this anchor (same text, different key)
                        $itemParts = array_reverse($itemParts);
                        $glued = '';
                        foreach ($itemParts as $itemPart) {
                            $glued = trim($itemPart . '/' . $glued, '/');
                            $createdAnchors[$glued] = $normalizedText;
                        }
                    }

                    $fileAnchor = '<a name="' . $normalizedText . '"></a>' . PHP_EOL;
                    $html = $fileAnchor . $html;

                    // Provide extended anchors (incl. filename)
                    preg_match_all('/(<h\d>)(.*?)(<\/h\d>)/i', $html, $matches);
                    foreach ($matches[0] as $subIndex => $headline) {
                        $text = $matches[2][$subIndex];
                        $normalizedText = $slugNormalizer->normalize($text, ['prefix' => $item . '-']);
                        if (!empty($normalizedText)) {
                            $createdAnchors[$item . '#' . $normalizedText] = $normalizedText;
                            $anchors = '<a name="' . $normalizedText . '"></a>' . PHP_EOL . '<a name="' . $slugNormalizer->normalize($text) . '"></a>' . PHP_EOL;
                            $itemParts = explode('/', $item);
                            if (count($itemParts) > 1) {
                                // Add relative variations of this anchor (same text, different key)
                                $itemParts = array_reverse($itemParts);
                                $glued = '';
                                foreach ($itemParts as $itemPart) {
                                    $glued = trim($itemPart . '/' . $glued, '/');
                                    $createdAnchors[$glued . '#' . $normalizedText] = $normalizedText;
                                    $createdAnchors[$glued . '#' . $slugNormalizer->normalize($text)] = $normalizedText;
                                }
                            }

                            $html = str_replace($headline, $anchors . $headline, $html);
                        }
                    }

                    // Shift headlines, based on last section level
                    if ($lastSectionLevel > 0) {
                        if ($lastSectionLevel + 5 <= 6) {
                            $html = str_replace('h5>', 'h' . ($lastSectionLevel + 5) . '>', $html);
                        }
                        if ($lastSectionLevel + 4 <= 6) {
                            $html = str_replace('h4>', 'h' . ($lastSectionLevel + 4) . '>', $html);
                        }
                        if ($lastSectionLevel + 3 <= 6) {
                            $html = str_replace('h3>', 'h' . ($lastSectionLevel + 3) . '>', $html);
                        }
                        if ($lastSectionLevel + 2 <= 6) {
                            $html = str_replace('h2>', 'h' . ($lastSectionLevel + 2) . '>', $html);
                        }
                        $html = str_replace('h1>', 'h' . ($lastSectionLevel + 1) . '>', $html);
                    }

                    // Convert relative file links
                    preg_match_all('/<a.*?href="(.*?)".*?>(.*?)<\/a>/i', $html, $matches);
                    foreach ($matches[0] as $subIndex => $linkTags) {
                        $href = $matches[1][$subIndex];
                        if (false !== strpos($href, '://') || 0 === strpos($href, '#')) {
                            continue; // skip any external links
                        }
                        $a = $this->configuration['baseUrl'] . '/' . dirname($item);
                        $b = $this->canonicalizePath($a . '/' . $href);
                        $convertedRelativeLinks[$href] = $b;
                    }

                    // Convert relative image paths
                    preg_match_all('/<img.*?src="(.*?)".*?\/>/i', $html, $matches);
                    foreach ($matches[0] as $subIndex => $linkTags) {
                        $src = $matches[1][$subIndex];
                        $path = $this->canonicalizePath($rootPath . '/' . dirname($item) . '/' . $src);
                        $html = str_replace($src, $path, $html);
                    }

                    $htmlParts[] = '<div class="file-break file-contents">' . $html . '</div>';
                } else {
                    $io->warning('Missing file "' . $item . '"!');
                }
            }
            $io->progressAdvance();
        }
        $io->progressFinish();

        $fullHtml = implode(PHP_EOL, $htmlParts);

        // Replace relative links with extended anchors
        preg_match_all('/<a.*?href="(.*?)".*?>(.*?)<\/a>/i', $fullHtml, $matches);
        foreach ($matches[0] as $index => $linkTags) {
            $href = $matches[1][$index];
            if (false !== strpos($href, '://')) {
                continue; // skip any external links
            }
            if (array_key_exists($href, $createdAnchors)) {
                $fullHtml = str_replace('href="' . $href . '"', 'href="#' . $createdAnchors[$href] . '"', $fullHtml);
            }
        }

        // Replace converted relative links
        preg_match_all('/<a.*?href="(.*?)".*?>(.*?)<\/a>/i', $fullHtml, $matches);
        foreach ($matches[0] as $index => $linkTags) {
            $href = $matches[1][$index];
            if (false !== strpos($href, '://') || 0 === strpos($href, '#')) {
                continue; // skip any external links
            }

            if (array_key_exists($href, $convertedRelativeLinks) && !array_key_exists($href, $createdAnchors)) {
                $fullHtml = str_replace($href, $convertedRelativeLinks[$href], $fullHtml);
            }
        }

        // TODO: Syntax highlighting

        // Output
        $io->writeln('Creating PDF...', SymfonyStyle::VERBOSITY_VERBOSE);
        $mpdf->WriteHTML($fullHtml);
        $output = $rootPath . DIRECTORY_SEPARATOR . $this->configuration['output'];
        $io->writeln('Writing PDF to <info>' . $output . '"</info>...');
        $mpdf->Output($output, \Mpdf\Output\Destination::FILE);
    }

    private function canonicalizePath(string $path): string
    {
        $parts = explode('/', $path);
        $stack = [];
        foreach ($parts as $seg) {
            if ('..' === $seg) {
                // Ignore this segment, remove last segment from stack
                array_pop($stack);
                continue;
            }

            if ('.' === $seg) {
                // Ignore this segment
                continue;
            }

            $stack[] = $seg;
        }

        return implode('/', $stack);
    }
}
