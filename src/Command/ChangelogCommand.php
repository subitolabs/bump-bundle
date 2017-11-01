<?php

namespace Subitolabs\Bundle\BumpBundle\Command;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ChangelogCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('subitolabs:changelog')
            ->setDescription('Write full changelog based on git tags and git logs.')
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
        $changeLogFile  = $input->getOption('changelog');

        $tags = $this->getTags();

        if (empty($tags)) {
            throw new Exception('No git tag found!');
        }

        $existingChangeLog = '';
        $previousTag = '';

        foreach($tags as $tag)
        {
            $tagLabel = $tag['tag'];

            if (empty($previousTag)) {
                $range = $tagLabel;
            } else {
                $range = $previousTag . '..' . $tagLabel;
            }

            $command = 'git log --pretty=format:\'{%n  "commit": "%H",%n  "abbreviated_commit": "%h",%n  "tree": "%T",%n  "abbreviated_tree": "%t",%n  "parent": "%P",%n  "abbreviated_parent": "%p",%n  "refs": "%D",%n  "encoding": "%e",%n  "subject": "%s",%n  "sanitized_subject_line": "%f",%n  "body": "%b",%n  "commit_notes": "%N",%n  "verification_flag": "%G?",%n  "signer": "%GS",%n  "signer_key": "%GK",%n  "author": {%n    "name": "%aN",%n    "email": "%aE",%n    "date": "%aD"%n  },%n  "commiter": {%n    "name": "%cN",%n    "email": "%cE",%n    "date": "%cD"%n  }%n},\' ' . $range . ' | sed "$ s/,$//" | sed \':a;N;$!ba;s/\r\n\([^{]\)/\\n\1/g\'| awk \'BEGIN { print("[") } { print($0) } END { print("]") }\'';

            $process = new Process($command);

            $process->run();

            if ($process->isSuccessful())
            {
                $buffer = json_decode($process->getOutput(), true);
            } else
            {
                throw new ProcessFailedException($process);
            }

            $changeLogLines = array_map(function ($log)
            {
                return sprintf('* %s: %s by %s (%s)', $log['author']['date'], $log['subject'], $log['author']['name'], $log['author']['email']);
            }, $buffer);

            $existingChangeLog = sprintf("## %s\n\n%s\n\n%s", $tagLabel, join(PHP_EOL, $changeLogLines), $existingChangeLog);

            $previousTag = $tagLabel;
        }

        file_put_contents($changeLogFile, $existingChangeLog);
    }

    protected function getTags() {
        $listTagCommand = 'git tag -l --format=\'{"date":"%(taggerdate:iso8601)","tag":"%(refname:short)"},\' | sed "$ s/,$//" | sed \':a;N;$!ba;s/\r\n\([^{]\)/\\n\1/g\'| awk \'BEGIN { print("[") } { print($0) } END { print("]") }\'';

        $process = new Process($listTagCommand);

        $process->run();

        if ($process->isSuccessful()) {
            $buffer = json_decode($process->getOutput(), true);
        } else {
            throw new ProcessFailedException($process);
        }

        usort($buffer, function($a, $b) {
           return strtotime($a['date']) > strtotime($b['date']);
        });

        return $buffer;
    }
}