<?php

declare(strict_types = 1);

namespace Armin\Md2Pdf;

use Armin\Md2Pdf\Service\ConfigManager;
use Armin\Md2Pdf\Service\Converter;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;

class Application extends SingleCommandApplication
{
    private const MODE_INIT = 'init';
    private const MODE_CHECK = 'check';
    private const MODE_UPDATE = 'update';
    private const MODE_BUILD = 'build';

    /** @var string[] */
    private array $modes = [self::MODE_INIT, self::MODE_CHECK, self::MODE_UPDATE, self::MODE_BUILD];
    private string $workingDirectory = '';
    private string $configPath = '';

    public function __construct(string $name = 'md2pdf')
    {
        parent::__construct($name);

        $this
            ->setName($name)
            ->setVersion(self::getApplicationVersionFromComposerJson())
            ->setCode([$this, 'executing'])

            ->addArgument('mode', InputArgument::REQUIRED, 'Available modes are: ' . implode(', ', $this->modes))

            ->addOption('dir', 'd', InputOption::VALUE_OPTIONAL, 'Working directory to scan', getcwd())
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Name of YAML file with configuration', 'md2pdf.yaml')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (empty($input->getArgument('mode'))) {
            $io = new SymfonyStyle($input, $output);
            $io->writeln('<comment>No mode choosen. Asking for mode to run...</comment>', OutputInterface::VERBOSITY_VERBOSE);
            $mode = $io->choice('Which mode would you like to run?', ['init', 'check', 'update', 'build']);
            $input->setArgument('mode', $mode);
        }
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->workingDirectory = realpath($input->getOption('dir')) ?: '';
        $this->configPath = $this->workingDirectory . DIRECTORY_SEPARATOR . $input->getOption('config');
    }

    protected function executing(Input $input, Output $output): int
    {
        if (empty($input->getArgument('mode')) || !in_array($mode = strtolower($input->getArgument('mode')), $this->modes, true)) {
            throw new \RuntimeException(sprintf('Given mode "%s" unknown. Available modes are: %s', $mode ?? '', implode(', ', $this->modes)));
        }

        $io = new SymfonyStyle($input, $output);

        $io->title('md2pdf v' . self::getApplicationVersionFromComposerJson());
        $io->writeln('Using configuration file: <info>' . $this->configPath . '</info>');
        $io->writeln(sprintf('<comment>Mode running: %s</comment>', (string)$input->getArgument('mode')), OutputInterface::VERBOSITY_VERBOSE);

        // Dispatching mode
        switch ($input->getArgument('mode')) {
            case self::MODE_INIT:
                return $this->init($io);
            case self::MODE_CHECK:
                return $this->check($io);
            case self::MODE_UPDATE:
                return $this->update($io);
            case self::MODE_BUILD:
                return $this->build($io);
        }

        return self::INVALID; // should never get reached
    }

    public function init(OutputStyle $io): int
    {
        // Check for existing yaml
        $configManager = new ConfigManager($this->configPath);
        if ($configManager->exists()) {
            throw new \RuntimeException(sprintf('There is already a configuration file "%s" existing.', $this->configPath));
        }

        // TODO Create new config file

        return self::SUCCESS;
    }

    public function check(OutputStyle $io): int
    {
        // TODO
        return self::SUCCESS;
    }

    public function update(OutputStyle $io): int
    {
        // TODO
        return self::SUCCESS;
    }

    public function build(OutputStyle $io): int
    {
        $configManager = new ConfigManager($this->configPath);

        $converter = new Converter($configManager->load(), $this->workingDirectory);
        $converter->convert($io);

        $io->success('Done.');

        return self::SUCCESS;
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
