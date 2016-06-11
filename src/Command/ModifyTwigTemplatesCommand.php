<?php

namespace Maba\Bundle\WebpackMigrationBundle\Command;

use Maba\Bundle\TwigTemplateModificationBundle\Service\FilesReplacer;
use Maba\Bundle\WebpackMigrationBundle\Service\NamedAssetsDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ModifyTwigTemplatesCommand extends Command
{
    private $filesReplacer;
    private $namedAssetsDumper;

    public function __construct(FilesReplacer $filesReplacer, NamedAssetsDumper $namedAssetsDumper)
    {
        parent::__construct('maba:webpack-migration:modify-twig-templates');

        $this->filesReplacer = $filesReplacer;
        $this->namedAssetsDumper = $namedAssetsDumper;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        if (!$helper->ask($input, $output, new ConfirmationQuestion(
            'This can lead to changing your files. Be sure to have VCS (like git) enabled and no current pending changes. Do you want to continue? [Ny] ',
            false
        ))) {
            return;
        }

        $namedAssets = $this->namedAssetsDumper->dump();
        if (count($namedAssets) > 0) {
            $output->writeln('Dumped named assets:');
            $output->writeln($namedAssets);
        }

        $this->filesReplacer->replace(function($filePath, $contents) use ($output) {
            $output->writeln('<info>Replaced content in ' . $filePath . '</info>');
        }, function (array $notices) use ($output) {
            $output->writeln($notices);
        });
    }
}
