<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Bundle\GeneratorBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\HttpKernel\KernelInterface;

use Sensio\Bundle\GeneratorBundle\Generator\BundleGenerator;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Command\GenerateBundleCommand;

use CampaignChain\Bundle\GeneratorBundle\Command\Validators;

/**
 *
 * Usage:
 * php app/console campaignchain:generate:module
 *
 */
class GenerateBundleCommand extends GenerateBundleCommand
{

    protected function configure()
    {
        $this
            ->setDefinition(array(
              new InputOption('bundle-name', '', InputOption::VALUE_REQUIRED, 'The bundle name'),
              new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The bundle namespace'),
              new InputOption('bundle-type', '', InputOption::VALUE_REQUIRED, 'The bundle type (channel, location, activity, operation)'),
              new InputOption('dir', '', InputOption::VALUE_REQUIRED, 'The bundle directory'),
              ))
            ->setName('campaignchain:generate:module')
            ->setDescription('Generates a new CampaignChain module skeleton as a bundle');
        
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            if (!$questionHelper->ask($input, $output, new ConfirmationQuestion($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true))) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        foreach (array('namespace', 'dir') as $option) {
            if (null === $input->getOption($option)) {
                throw new \RuntimeException(sprintf('The "%s" option must be provided.', $option));
            }
        }

        // validate the namespace, but don't require a vendor namespace
        $namespace = Validators::validateBundleNamespace($input->getOption('namespace'), false);
        if (!$bundle = $input->getOption('bundle-name')) {
            $bundle = strtr($namespace, array('\\' => ''));
        }
        $bundle = Validators::validateBundleName($bundle);
        $dir = Validators::validateTargetDir($input->getOption('dir'), $bundle, $namespace);
        $format = 'yml';
        $structure = 'yes';

        $questionHelper->writeSection($output, 'Bundle generation');

        if (!$this->getContainer()->get('filesystem')->isAbsolutePath($dir)) {
            $dir = getcwd().'/'.$dir;
        }

        $generator = $this->getGenerator();
        $generator->generate($namespace, $bundle, $dir, $format, $structure);

        $output->writeln('Generating the bundle code: <info>OK</info>');

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        // check that the namespace is already autoloaded
        $runner($this->checkAutoloader($output, $namespace, $bundle, $dir));

        // register the bundle in the Kernel class
        $runner($this->updateKernel($questionHelper, $input, $output, $this->getContainer()->get('kernel'), $namespace, $bundle));

        // routing
        $runner($this->updateRouting($questionHelper, $input, $output, $bundle, $format));

        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the CampaignChain bundle generator');

        /** bundle name **/
        $bundleName = null;
        
        try {
            $bundleName = $input->getOption('bundle') ? Validators::validateBundleName($input->getOption('bundle')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }

        if (null === $bundleName) {        
            $output->writeln(array(
                'Each CampaignChain bundle needs a name (like <comment>acme/channel-google</comment>).',
                '',
                'This is the application name or vendor name, followed by a separating slash (/), ',
                'then the module type followed by a dash and the bundle\'s purpose.',
                '',
                'See http://doc.campaignchain.com/current/developer/book/modules.html for more details on bundle naming conventions.'
            ));
                    
            $question = new Question($questionHelper->getQuestion('Bundle name', $bundleName), $bundleName);
            $question->setValidator(function ($answer) {
                 return Validators::validateBundleName($answer, false);
            });
            $bundleName = $questionHelper->ask($input, $output, $question);
            $input->setOption('bundle-name', $bundleName);
        }  
        
        $outerSeg = explode('/', $bundleName);
        $innerSeg = explode('-', $outerSeg[1]);
        $derivedVendor = strtolower($outerSeg[0]);
        $derivedType = strtolower($innerSeg[0]);
        $derivedLabel = strtolower($innerSeg[1]);
        
        /** bundle namespace **/
        $namespace = null;
        
        try {
            $namespace = $input->getOption('namespace') ? Validators::validateBundleNamespace($input->getOption('namespace')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }
        
        $recommendedNamespace = ucfirst($derivedVendor) . '/CampaignChain/' . ucfirst($derivedType) . '/' . ucfirst($derivedLabel) . 'Bundle';
        
        if (null === $namespace) {
            $output->writeln(array(
                'Based on the bundle name and type, we suggest the namespace ',
                '<comment>'.$recommendedNamespace.'</comment>.'
            ));
            $question = new Question($questionHelper->getQuestion('Namespace name',  $recommendedNamespace), $recommendedNamespace);
            $question->setValidator(function ($answer) {
                return Validators::validateBundleNamespace($answer, false);
            });
            $bundle = $questionHelper->ask($input, $output, $question);
            $input->setOption('namespace', $bundle);
        }
        
        /** bundle type **/
        $bundleType = null;     
        
        try {
            $bundleType = $input->getOption('bundle-type') ? Validators::validateBundleType($input->getOption('bundle-type')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }
        
        $recommendedType = $derivedType;
        if (null === $bundleType) {
            $output->writeln(array(
                'Based on the bundle name, we assume this is a ' . ucfirst($derivedType) . ' bundle.',
            ));
            $question = new Question($questionHelper->getQuestion('Bundle type (channel, location, operation, activity)', $recommendedType), $recommendedType);
            
            $question->setValidator(function ($answer) {
                return Validators::validateBundleType($answer, false);
            });
            $type = $questionHelper->ask($input, $output, $question);
            $input->setOption('bundle-type', $bundleType);
        }  

        /** bundle directory **/
        $dir = null;
        if (null === $dir) {
            $dir = dirname($this->getContainer()->getParameter('kernel.root_dir')).'/src';
            $output->writeln(array(
                'The bundle can be generated anywhere. The suggested default directory uses',
                'the standard conventions.',
            ));
            $question = new Question($questionHelper->getQuestion('Target directory', $dir), $dir);
            $question->setValidator(function ($dir) use ($bundle, $namespace) {
                return Validators::validateTargetDir($dir, $bundle, $namespace);
            });
            $dir = $questionHelper->ask($input, $output, $question);
            $input->setOption('dir', $dir);
        }        
    }

    protected function checkAutoloader(OutputInterface $output, $namespace, $bundle, $dir)
    {
        parent::checkAutoloader($output, $namespace, $bundle, $dir);
    }

    protected function updateKernel(QuestionHelper $questionHelper, InputInterface $input, OutputInterface $output, KernelInterface $kernel, $namespace, $bundle)
    {
        parent::updateKernel($questionHelper, $input, $output, $kernel, $namespace, $bundle);
    }

    protected function updateRouting(QuestionHelper $questionHelper, InputInterface $input, OutputInterface $output, $bundle, $format)
    {
        parent::updateRouting($questionHelper, $input, $output, $bundle, $format);
    }

    protected function createGenerator()
    {
        return new BundleGenerator($this->getContainer()->get('filesystem'));
    }
}
