<?php

namespace tigerman\DoctrineGenerateModelsBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\EntityRepositoryGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use tigerman\DoctrineGenerateModelsBundle\Doctrine\ORM\Tools\EntityGenerator;

class GenerateModelsDoctrineCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $help = <<<HELP
The <info>doctrine:generate:models</info> command generates model classes
from your mapping information:

  <info>php app/console doctrine:generate:entities MyCustomBundle</info>

HELP;
        $this
            ->setName('doctrine:generate:models')
            ->setAliases(array('generate:doctrine:models'))
            ->setDescription('Generate model classes from your mapping information')
            ->addArgument('bundle', null, 'A bundle name')
            ->addOption('without-listeners', null, InputOption::VALUE_OPTIONAL, '', true)
            ->setHelp($help)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($bundle_name = $input->getArgument('bundle')) {
            /** @var $em \Doctrine\ORM\EntityManager */
            $em = $this->getContainer()->get('doctrine')->getManager();
            $wl = $input->getOption('without-listeners');
            if ($wl) {
                $eventManager = $em->getEventManager();
                if ($eventManager->hasListeners(Events::loadClassMetadata)) {
                    foreach ($eventManager->getListeners(Events::loadClassMetadata) as $listener) {
                        $eventManager->removeEventListener(Events::loadClassMetadata, $listener);
                    }
                }
            }

            /** @var $application \Symfony\Bundle\FrameworkBundle\Console\Application */
            $application = $this->getApplication();
            $bundle = $application->getKernel()->getBundle($bundle_name);
            $output->writeln('Generating models '.($wl ? 'without listeners' : 'with listeners').' for bundle "<info>'.$bundle->getName().'</info>"');

            $driver = null;
            /** @var $metadriver_impl \Doctrine\ORM\Mapping\Driver\DriverChain */
            $metadriver_impl = $em->getConfiguration()->getMetadataDriverImpl();
            foreach ($metadriver_impl->getDrivers() as $k => $v) {
                if (strpos($bundle->getNamespace().'\Entity', $k) === 0) {
                    /** @var $driver \Doctrine\ORM\Mapping\Driver\SimplifiedYamlDriver */
                    $driver = $v;
                }
            }
            if (!$driver) {
                throw new \Exception('Driver not found');
            }

            /** @var $doctrine \Doctrine\Bundle\DoctrineBundle\Registry */
            $doctrine = $this->getContainer()->get('doctrine');
            $manager = new DisconnectedMetadataFactory($doctrine);
            $metadata = $manager->getBundleMetadata($bundle);

            $generator = $this->getEntityGenerator();
            $repoGenerator = new EntityRepositoryGenerator();
            foreach ($metadata->getMetadata() as $m) {
                /** @var $m \Doctrine\ORM\Mapping\ClassMetadataInfo */
                $output->writeln('  > generating '.($wl ? 'without listeners' : 'with listeners').' <comment>'.$m->getName().'</comment>');
                $element = $driver->getElement($m->getName());
                if (isset($element['extends'])) {
                    $generator->setClassToExtend($element['extends']);
                }
                $generator->generate(array($m), $metadata->getPath());
                $generator->setClassToExtend(null);
                if ($m->customRepositoryClassName && false !== strpos($m->customRepositoryClassName, $metadata->getNamespace())) {
                    $repoGenerator->writeEntityRepositoryClass($m->customRepositoryClassName, $metadata->getPath());
                }
            }

            $modelsPath = $bundle->getPath().DIRECTORY_SEPARATOR.'Model'.DIRECTORY_SEPARATOR;
            $entitiesPath = $bundle->getPath().DIRECTORY_SEPARATOR.'Entity'.DIRECTORY_SEPARATOR;
            if (!file_exists($modelsPath)) {
                mkdir($modelsPath);
            }
            foreach (new \DirectoryIterator($modelsPath) as $file) {
                /** @var $file \DirectoryIterator */
                if (!$file->isDot()) {
                    unlink($file->getPathname());
                }
            }

            $exists = array();
            foreach ($metadata->getMetadata() as $m) {
                /** @var $m \Doctrine\ORM\Mapping\ClassMetadataInfo */
                $element = $driver->getElement($m->getName());
                $output->writeln('  > transforming '.($wl ? 'without listeners' : 'with listeners').' <comment>'.$m->getName().'</comment>');
                $ePath = $metadata->getPath().DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $m->getName()).'.php';
                $ePath = realpath($ePath);
                $mPath = $modelsPath.basename($ePath);
                $eName = basename($ePath, '.php');

                $model = file_get_contents($ePath);
                if (isset($element['extends'])) {
                    $model = str_replace($element['extends'], str_replace('Entity', '@Entity', $element['extends']), $model);
                }
                $model = str_replace(' private ', ' protected ', $model);
                $model = str_replace("\n".'class ', "\n".'abstract class ', $model);
                $model = str_replace('\Entity;', '\Model;', $model);
                $model = str_replace('\Entity\\'.$eName, '\Model\\'.$eName, $model);
                if (isset($element['extends'])) {
                    $model = str_replace('@Entity', 'Entity', $model);
                }
                $model = str_replace("\n".'use Doctrine\ORM\Mapping as ORM;'."\n", '', $model);

                $model = str_ireplace('* @preRemove', '* preRemove', $model);
                $model = str_ireplace('* @ORM\preRemove', '* ORM\preRemove', $model);
                $model = str_ireplace('* @postRemove', '* postRemove', $model);
                $model = str_ireplace('* @ORM\postRemove', '* ORM\postRemove', $model);
                $model = str_ireplace('* @prePersist', '* prePersist', $model);
                $model = str_ireplace('* @ORM\prePersist', '* ORM\prePersist', $model);
                $model = str_ireplace('* @postPersist', '* postPersist', $model);
                $model = str_ireplace('* @ORM\postPersist', '* ORM\postPersist', $model);
                $model = str_ireplace('* @preUpdate', '* preUpdate', $model);
                $model = str_ireplace('* @ORM\preUpdate', '* ORM\preUpdate', $model);
                $model = str_ireplace('* @postUpdate', '* postUpdate', $model);
                $model = str_ireplace('* @ORM\postUpdate', '* ORM\postUpdate', $model);
                $model = str_ireplace('* @postLoad', '* postLoad', $model);
                $model = str_ireplace('* @ORM\postLoad', '* ORM\postLoad', $model);
                $model = str_replace('* @loadClassMetadata', '* loadClassMetadata', $model);
                $model = str_replace('* @onFlush', '* onFlush', $model);

                $model = str_replace('* @var decimal', '* @var float', $model);
                $model = str_replace('* @param decimal', '* @param float', $model);
                $model = str_replace('* @return decimal', '* @return float', $model);
                $model = str_replace('* @var text', '* @var string', $model);
                $model = str_replace('* @param text', '* @param string', $model);
                $model = str_replace('* @return text', '* @return string', $model);
                $model = str_replace('* @var datetime', '* @var \\DateTime', $model);
                $model = str_replace('* @param datetime', '* @param \\DateTime', $model);
                $model = str_replace('* @return datetime', '* @return \\DateTime', $model);
                $model = str_replace('* @var My', '* @var \\My', $model);
                $model = str_replace('* @param My', '* @param \\My', $model);
                $model = str_replace('* @return My', '* @return \\My', $model);
                $model = str_replace('* @var Doctrine', '* @var \\Doctrine', $model);
                $model = str_replace('* @param Doctrine', '* @param \\Doctrine', $model);
                $model = str_replace('* @return Doctrine', '* @return \\Doctrine', $model);

                $model = preg_replace('#\* @var enum[a-z]+#', '\* @var string', $model);
                $model = preg_replace('#\* @param enum[a-z]+#', '\* @param string', $model);
                $model = preg_replace('#\* @return enum[a-z]+#', '\* @return string', $model);

                file_put_contents($mPath, $model);

                unlink($ePath);
                if (file_exists($ePath.'~')) {
                    rename($ePath.'~', $ePath);
                } else {
                    $bNamespace = $bundle->getNamespace();
                    $content = <<<CONTENT
<?php

namespace {$bNamespace}\Entity;

use {$bNamespace}\Model\\{$eName} as {$eName}Model;

class {$eName} extends {$eName}Model
{
}

CONTENT;
                    file_put_contents($ePath, $content);
                }
                $exists[] = $ePath;
            }
            foreach (new \DirectoryIterator($entitiesPath) as $file) {
                /** @var $file \DirectoryIterator */
                if (!$file->isDot() && !in_array($file->getPathname(), $exists)) {
                    unlink($file->getPathname());
                }
            }

            if ($wl) {
                $command = new GenerateModelsDoctrineCommand();
                $command->setApplication($application);
                $arguments = array(
                    'bundle'              => $bundle_name,
                    '--without-listeners' => false,
                );
                $input = new ArrayInput($arguments);
                $command->run($input, $output);
            }
        } else {
            /** @var $application \Symfony\Bundle\FrameworkBundle\Console\Application */
            $application = $this->getApplication();
            $kernel = $application->getKernel();
            $srcDir = realpath($kernel->getRootDir().'/../src');
            $bundles = $kernel->getBundles();
            foreach ($bundles as $bundle) {
                if (strlen($srcDir) > 0 && substr_compare($bundle->getPath(), $srcDir, 0, strlen($srcDir)) === 0) {
                    if (is_dir($bundle->getPath().'/Resources/config/doctrine/')) {
                        $command = new GenerateModelsDoctrineCommand();
                        $command->setApplication($application);
                        $arguments = array(
                            'bundle'  => $bundle->getName(),
                        );
                        $input = new ArrayInput($arguments);
                        $command->run($input, $output);
                    }
                }
            }
        }
    }

    protected function getEntityGenerator()
    {
        $entityGenerator = new EntityGenerator();
        $entityGenerator->setGenerateStubMethods(true);
        $entityGenerator->setRegenerateEntityIfExists(true);
        $entityGenerator->setBackupExisting(true);

        return $entityGenerator;
    }
}
