<?php

namespace Subitolabs\Bundle\BumpBundle\Command;

use Composer\Semver\VersionParser;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Class BumpCommand
 * @package Subitolabs\Bundle\BumpBundle\Command
 */
class BumpCommand extends ContainerAwareCommand
{
    /**
     * @var OutputInterface $output
     */
    private $output;

    /**
     * @var bool
     */
    private $dryRun;

    protected function configure()
    {
        $this
            ->setName('subitolabs:bump')
            ->setDescription('Bump version according semantic versioning (http://semver.org/) - create git tag.')
            //->addArgument('action', InputArgument::REQUIRED, 'Action: show/bump')
            ->addArgument('env', InputArgument::REQUIRED, 'Environment')
            ->addArgument('position', InputArgument::OPTIONAL, 'Position to increment: 0=nothing(default), 1=MAJOR, 2=MINOR, 3=PATCH', 0)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Set to not alter data and git something')
            ->addOption('message', null, InputOption::VALUE_OPTIONAL, 'Tag message', 'Bump to {{tag}} with Subitolabs bump bundle')
            ->addOption('tag', null, InputOption::VALUE_OPTIONAL, 'How tag is made', '{{env}}-{{version}}')
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'File to write version info (JSON encoded)', './app/config/version.yml')
            ->addOption('changelog', null, InputOption::VALUE_OPTIONAL, 'CHANGELOG.md path', './CHANGELOG.md')
        ;
    }

    /**
     * Execute command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output   = $output;
        $this->dryRun   = (bool) $input->getOption('dry-run');

        $env            = $input->getArgument('env');
        $position       = (int) $input->getArgument('position');
        $messageFormat  = $input->getOption('message');
        $inDataFile     = $input->getOption('file');
        $tagFormat      = $input->getOption('tag');
        $changeLogFile  = $input->getOption('changelog');

        $dataFileData   = $this->dataFileRead($inDataFile);
        $version        = $this->alterVersion($dataFileData['parameters']['project_version'], $position);
        $tag            = $this->renderTemplate($tagFormat, $env, $version);
        $message        = $this->renderTemplate($messageFormat, $env, $version, $tag);

        $this->buildChangelog($changeLogFile, $dataFileData['parameters']['project_git_tag'], $tag);

        $this->dataFileWrite($inDataFile, $version, $tag);
        $this->execGitCommands($inDataFile, $changeLogFile, $version, $message, $tag);
    }

    protected function buildChangelog($changeLogFile, $fromTag, $toTag) {
        $listTagCommand = 'git tag -l --format=\'{"date":"%(taggerdate:iso8601)","tag":"%(refname:short)"},\' | sed "$ s/,$//" | sed \':a;N;$!ba;s/\r\n\([^{]\)/\\n\1/g\'| awk \'BEGIN { print("[") } { print($0) } END { print("]") }\'';
        $command = 'git log --pretty=format:\'{%n  "commit": "%H",%n  "abbreviated_commit": "%h",%n  "tree": "%T",%n  "abbreviated_tree": "%t",%n  "parent": "%P",%n  "abbreviated_parent": "%p",%n  "refs": "%D",%n  "encoding": "%e",%n  "subject": "%s",%n  "sanitized_subject_line": "%f",%n  "body": "%b",%n  "commit_notes": "%N",%n  "verification_flag": "%G?",%n  "signer": "%GS",%n  "signer_key": "%GK",%n  "author": {%n    "name": "%aN",%n    "email": "%aE",%n    "date": "%aD"%n  },%n  "commiter": {%n    "name": "%cN",%n    "email": "%cE",%n    "date": "%cD"%n  }%n},\' ' . $fromTag . '..HEAD | sed "$ s/,$//" | sed \':a;N;$!ba;s/\r\n\([^{]\)/\\n\1/g\'| awk \'BEGIN { print("[") } { print($0) } END { print("]") }\'';


        $process = new Process($command);

        $process->run();

        if ($process->isSuccessful()) {
            $buffer = json_decode($process->getOutput(), true);
        } else {
            throw new ProcessFailedException($process);
        }

        $changeLogLines = array_map(function($log) {
            return sprintf('* %s: %s by %s (%s)', $log['author']['date'], $log['subject'], $log['author']['name'], $log['author']['email']);
        }, $buffer);

        if (file_exists($changeLogFile)) {
            $existingChangeLog = file_get_contents($changeLogFile);
        } else {
            $existingChangeLog = '';
        }

        file_put_contents($changeLogFile, sprintf("## %s\n\n%s\n\n%s", $toTag, join(PHP_EOL, $changeLogLines), $existingChangeLog));
    }

    protected function execGitCommands($dataFile, $changeLogFile, $version, $message, $tag) {
        $this->execShell(sprintf('git commit %s -m "Bump version to %s"', $dataFile . ' ' . (file_exists($changeLogFile) ? $changeLogFile : ''), $version));
        $this->execShell(sprintf('git tag -am "%s" %s', $message, $tag));
        $this->execShell(sprintf('git push origin %s', $tag));
    }

    protected function execShell($command) {
        $this->output->writeln(sprintf('Execute "%s"', $command), OutputInterface::VERBOSITY_VERY_VERBOSE);

        if (!$this->dryRun) {
            exec($command);
        }
    }

    protected function alterVersion($version, $position) {

        if (empty($version)) {
            $version = '0.0.0';
        }

        if ($position > 0) {
            $position--;

            $buffer = explode('.', $version);

            for ($i = count($buffer) - 1; $i >= 0; $i--) {
                if ($i > $position) {
                    $buffer[$i] = 0;
                } elseif ($i === $position) {
                    $buffer[$i]++;
                }
            }

            $newVersion = join('.', $buffer);

            $this->output->writeln(sprintf('From %s to %s', $version, $newVersion), OutputInterface::VERBOSITY_VERBOSE);

            return $newVersion;
        }

        return $version;
    }


    /**
     * Build the tag label.
     *
     * @param string    $format
     * @param string    $env
     * @param string    $version
     * @param string    $tag
     * @return string
     */
    protected function renderTemplate($format, $env, $version, $tag = '') {
        $templateVariables = [
            'env' => $env,
            'version' => $version,
            'tag' => $tag
        ];

        $buffer = $format;

        foreach($templateVariables as $k => $v) {
            $buffer = str_replace('{{' . $k . '}}', $v, $buffer);
        }

        return $buffer;
    }


    /**
     * Read/parse data file.
     *
     * @param string    $filePath
     * @return array
     */
    protected function dataFileRead($filePath) {
        if (!file_exists($filePath)) {
            $this->output->writeln(sprintf('Create data file at "%s"', $filePath), OutputInterface::VERBOSITY_VERBOSE);

            return [
                'parameters' => [
                    'project_version' => '0.0.0',
                    'project_git_tag' => ''
                ]
            ];
        } else {
            $buffer = Yaml::parse(file_get_contents($filePath), true);

            $this->output->writeln(sprintf('Read data file "%s"', json_encode($buffer)), OutputInterface::VERBOSITY_VERBOSE);

            return $buffer;
        }
    }


    /**
     * Write data file.
     *
     * @param string    $filePath
     * @param string    $version
     * @param string    $tag
     */
    protected function dataFileWrite($filePath, $version, $tag) {
        $buffer = [
            'parameters' => [
                'project_version' => $version,
                'project_git_tag' => $tag
            ]
        ];

        $this->output->writeln(sprintf('Write data file %s', json_encode($buffer)), OutputInterface::VERBOSITY_VERBOSE);

        if (!$this->dryRun) {
            file_put_contents($filePath, Yaml::dump($buffer));
        }
    }

}
