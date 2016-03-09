<?php

/**
 * This file is part of the Spryker Demoshop.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Pyz\Zed\Installer\Business\Icecat\Installer;

use Pyz\Zed\Installer\Business\Icecat\Importer\IcecatImporterInterface;
use Spryker\Shared\Gui\ProgressBar\ProgressBarBuilder;
use Spryker\Shared\Library\BatchIterator\CountableIteratorInterface;
use Spryker\Zed\Messenger\Business\Model\MessengerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractIcecatInstaller implements IcecatInstallerInterface
{

    const BAR_TITLE = 'barTitle';

    /**
     * @var \Spryker\Shared\Library\BatchIterator\CountableIteratorInterface
     */
    protected $batchIterator;

    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar
     */
    protected $progressBar;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var \Spryker\Zed\Messenger\Business\Model\MessengerInterface
     */
    protected $messenger;

    /**
     * @var string
     */
    protected $dataDirectory;

    /**
     * @var array|\Pyz\Zed\Installer\Business\Icecat\Importer\IcecatImporterInterface[]
     */
    protected $importerCollection = [];

    /**
     * @return \Spryker\Shared\Library\BatchIterator\CountableIteratorInterface
     */
    abstract protected function buildBatchIterator();

    /**
     * @return string
     */
    abstract public function getTitle();

    /**
     * @param array|\Pyz\Zed\Installer\Business\Icecat\Importer\IcecatImporterInterface[] $importerCollection
     * @param string $dataDirectory
     */
    public function __construct(array $importerCollection, $dataDirectory)
    {
        $this->importerCollection = $importerCollection;
        $this->dataDirectory = $dataDirectory;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Spryker\Zed\Messenger\Business\Model\MessengerInterface $messenger
     *
     * @return void
     */
    public function install(OutputInterface $output, MessengerInterface $messenger)
    {
        $importersToExecute = $this->excludeInstalled();

        $this->displayProgressWhileCountingBatchCollectionSize($output);
        $this->setupScope($output, $messenger);

        $this->beforeInstall();
        $this->batchInstall($this->batchIterator, $importersToExecute);
        $this->afterInstall();
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Spryker\Zed\Messenger\Business\Model\MessengerInterface $messenger
     *
     * @return void
     */
    protected function setupScope(OutputInterface $output, MessengerInterface $messenger)
    {
        $this->output = $output;
        $this->messenger = $messenger;

        $this->batchIterator = $this->buildBatchIterator();
        $this->progressBar = $this->generateProgressBar($output, $this->batchIterator->count());
    }

    /**
     * @return void
     */
    protected function beforeInstall()
    {
        $this->progressBar->setMessage($this->getTitle(), 'barTitle');
        $this->progressBar->start();
        $this->progressBar->advance(0);
    }

    /**
     * @param \Spryker\Shared\Library\BatchIterator\CountableIteratorInterface $batchIterator
     * @param array|\Pyz\Zed\Installer\Business\Icecat\Importer\IcecatImporterInterface[] $importersToExecute
     *
     * @return void
     */
    protected function batchInstall(CountableIteratorInterface $batchIterator, array $importersToExecute)
    {
        foreach ($batchIterator as $batchCollection) {
            foreach ($batchCollection as $itemToImport) {
                $this->runImporters($itemToImport, $importersToExecute);
            }
        }
    }

    /**
     * @param array $itemToImport
     * @param array|\Pyz\Zed\Installer\Business\Icecat\Importer\IcecatImporterInterface[] $importerCollection
     *
     * @return void
     */
    protected function runImporters(array $itemToImport, array $importerCollection)
    {
        foreach ($importerCollection as $type => $importer) {
            $this->progressBar->setMessage($importer->getTitle(), self::BAR_TITLE);
            $this->progressBar->display();

            $importer->beforeImport();
            $importer->import($itemToImport);
            $importer->afterImport();
        }

        $this->progressBar->advance(1);
    }

    /**
     * @return void
     */
    protected function afterInstall()
    {
        $this->progressBar->setMessage($this->getTitle(), self::BAR_TITLE);
        $this->progressBar->finish();

        $this->output->writeln('');
    }

    /**
     * @return array|\Pyz\Zed\Installer\Business\Icecat\Importer\IcecatImporterInterface[]
     */
    protected function excludeInstalled()
    {
        return array_filter($this->importerCollection, function (IcecatImporterInterface $importer) {
            return !$importer->isImported();
        });
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param int $count
     *
     * @return \Symfony\Component\Console\Helper\ProgressBar
     */
    protected function generateProgressBar(OutputInterface $output, $count)
    {
        $builder = new ProgressBarBuilder($output, $count, $this->getTitle());
        return $builder->build();
    }

    /**
     * Display progress while counting data for real progress bar
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function displayProgressWhileCountingBatchCollectionSize(OutputInterface $output)
    {
        $builder = new ProgressBarBuilder($output, 1, $this->getTitle());
        $progressBar = $builder->build();
        $progressBar->setFormat(" * %barTitle%\x0D ");
        $progressBar->start();
        $progressBar->advance();
        $progressBar->finish();
    }

    /**
     * @return bool
     */
    public function isInstalled()
    {
        foreach ($this->importerCollection as $importer) {
            if (!$importer->isImported()) {
                return false;
            }
        }

        return true;
    }

}