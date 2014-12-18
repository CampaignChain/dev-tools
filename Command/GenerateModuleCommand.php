<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\GeneratorBundle\Command;

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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\Container;

use Sensio\Bundle\GeneratorBundle\Generator\BundleGenerator;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Command\GenerateBundleCommand;

use CampaignChain\GeneratorBundle\Command\Validators;
use CampaignChain\GeneratorBundle\Generator\ModuleGenerator;

/**
 *
 * Usage:
 * php app/console campaignchain:generate:module
 *
 */
class GenerateModuleCommand extends GenerateBundleCommand
{

    protected function configure()
    {
        $this
            ->setDefinition(array(
              new InputOption('module-type', '', InputOption::VALUE_REQUIRED, 'The module type'),
              new InputOption('module-id', '', InputOption::VALUE_REQUIRED, 'The module identifier'),
              new InputOption('module-description', '', InputOption::VALUE_REQUIRED, 'The module description'),
              new InputOption('author-name', '', InputOption::VALUE_REQUIRED, 'The author name'),
              new InputOption('author-email', '', InputOption::VALUE_OPTIONAL, 'The author email address'),
              new InputOption('module-license', '', InputOption::VALUE_REQUIRED, 'The module license'),
              new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The bundle namespace'),
              new InputOption('bundle-name', '', InputOption::VALUE_REQUIRED, 'The bundle name'),
              new InputOption('package-name', '', InputOption::VALUE_REQUIRED, 'The Composer package name'),
              new InputOption('operation-owns-location', '', InputOption::VALUE_OPTIONAL, 'The \'owns_location\' parameter (for operation modules only)'),
              new InputOption('channels-for-activity', '', InputOption::VALUE_OPTIONAL, 'The \'channels\' parameter (for activity modules only)'),
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

        foreach (array('module-type', 'module-id', 'module-description', 'author-name', 'module-license', 'bundle-name', 'namespace', 'package-name', 'dir') as $option) {
            if (null === $input->getOption($option)) {
                throw new \RuntimeException(sprintf('The "%s" option must be provided.', $option));
            }
        }

        $namespace = Validators::validateBundleNamespace($input->getOption('namespace'), false);
        if (!$bundleName = $input->getOption('bundle-name')) {
          $bundleName = strtr($namespace, array('\\' => ''));
        }
        $bundleName = Validators::validateBundleName($input->getOption('bundle-name'), false);
        $dir = Validators::validateTargetDir($input->getOption('dir'), $bundleName, $namespace);
        $format = 'yml';
        $structure = 'yes';
        $moduleType = Validators::validateModuleType($input->getOption('module-type'), false);
        $moduleIdentifier = Validators::validateModuleIdentifier($input->getOption('module-id'), false);
        $moduleDescription = Validators::validateDescription($input->getOption('module-description'), false);
        $moduleLicense = Validators::validateModuleLicense($input->getOption('module-license'), false);
        $authorName = Validators::validateAuthorName($input->getOption('author-name'), false);
        $authorEmail = Validators::validateAuthorEmail($input->getOption('author-email'), false);
        $packageName = Validators::validatePackageName($input->getOption('package-name'), false);
        $operationOwnsLocation = null;
        if ($moduleType == 'operation') {
            $operationOwnsLocation = Validators::validateOperationOwnsLocation($input->getOption('operation-owns-location'), false);
        }
        $channelsForActivity = null;
        if ($moduleType == 'activity') {
            $channelsForActivity = Validators::validateChannelsForActivity($input->getOption('channels-for-activity'), false);
        }
        
        $questionHelper->writeSection($output, 'Bundle generation');

        if (!$this->getContainer()->get('filesystem')->isAbsolutePath($dir)) {
            $dir = getcwd().'/'.$dir;
        }

        $generator = $this->getGenerator();
        
        $generator->generate($namespace, $bundleName, $dir, $format, $structure);
        $output->writeln('Generating the bundle: <info>OK</info>');     
        
        $generator->generateConf($namespace, $bundleName, $dir, $moduleType, $moduleIdentifier, $moduleDescription, $moduleLicense, $authorName, $authorEmail, $packageName, $operationOwnsLocation, $channelsForActivity);
        $output->writeln('Generating the CampaignChain configuration files: <info>OK</info>');
               
        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);
        
        // check that the namespace is already autoloaded
        $runner($this->checkAutoloader($output, $namespace, $bundleName, $dir));

        // register the bundle in the Kernel class
        //$runner($this->updateKernel($questionHelper, $input, $output, $this->getContainer()->get('kernel'), $namespace, $bundleName));

        // routing
        //$runner($this->updateRouting($questionHelper, $input, $output, $bundleName, $format));

        // reminder
        $runner($this->setReminders($output));
        
        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the CampaignChain bundle generator');
        
        /** module type **/
        $moduleType = null;             
        try {
            $moduleType = $input->getOption('module-type') ? Validators::validateModuleType($input->getOption('module-type')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $moduleType) {
            $output->writeln(array(
              '',
              'CampaignChain\'s core can be extended through various types of modules,',
              'each covering a certain feature set. The following pre-defined types exist:',
              '',
              '* \'activity\', e.g. post on Facebook or Twitter.',
              '* \'campaign\', to develop custom campaign functionality (e.g. nurtured campaigns).',
              '* \'channel\', to connect to channels such as Facebook or Twitter.',
              '* \'location\', to manage e.g. various Facebook pages.',
              '* \'milestone\', e.g. to develop a new kind of milestone besides the default one with a due date.',
              '* \'operation\', similar to Activity module type.',
              '* \'report\', to create custom analytics, budget or sales reports for ROI monitoring.',
              '* \'security\', e.g. functionality for channels to log in to third-party systems.',
              '* \'distribution\', an aggregation of bundles and system-wide configuration,', 
              '   e.g. the CampaignChain Community Edition.',
              ''            
            ));
            $question = new Question($questionHelper->getQuestion('Module type', $moduleType), $moduleType);            
            $question->setValidator(function ($answer) {
                return Validators::validateModuleType($answer, false);
            });
            $moduleType = $questionHelper->ask($input, $output, $question);
            $input->setOption('module-type', $moduleType);
        }  

        /** module identifier **/
        $moduleIdentifier = null;             
        try {
            $moduleIdentifier = $input->getOption('module-id') ? Validators::validateModuleIdentifier($input->getOption('module-id')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $moduleIdentifier) {
            $output->writeln(array(
              '',
              'The recommended syntax of the module identifier is to use dashes (-)',
              'to separate words. The identifier should start with an application or',
              'vendor name followed by a string that best captures the purpose of the module',
              '(like <comment>campaignchain-twitter-update-status</comment>).',
              ''              
            ));
            $question = new Question($questionHelper->getQuestion('Module identifier', $moduleIdentifier), $moduleIdentifier);            
            $question->setValidator(function ($answer) {
                return Validators::validateModuleIdentifier($answer, false);
            });
            $moduleIdentifier = $questionHelper->ask($input, $output, $question);
            $input->setOption('module-id', $moduleIdentifier);
        }  
        $derivedPurpose = '';
        $idSegs = explode('-', $moduleIdentifier);
        foreach ($idSegs as $seg) {
          $derivedPurpose .= ucfirst(strtolower($seg));
        }

        /** module description **/
        $moduleDescription = null;             
        try {
            $moduleDescription = $input->getOption('module-description') ? Validators::validateDescription($input->getOption('module-description')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $moduleDescription) {
            $output->writeln(array(
              '',
              'The module description is a human-readable label that will be shown', 
              'in CampaignChain’s graphical user interface',
              '(like <comment>Update Twitter Status</comment>).',
              ''              
            ));
            $question = new Question($questionHelper->getQuestion('Module description', $moduleDescription), $moduleDescription);            
            $question->setValidator(function ($answer) {
                return Validators::validateDescription($answer, false);
            });
            $moduleDescription = $questionHelper->ask($input, $output, $question);
            $input->setOption('module-description', $moduleDescription);
        }  
        
        /** owns_location value **/
        if ($moduleType == 'operation') {
            $operationOwnsLocation = null;             
            try {
                $moduleDescription = $input->getOption('operation-owns-location') ? Validators::validateOperationOwnsLocation($input->getOption('operation-owns-location')) : null;
            } catch (\Exception $error) {
                $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
            }        
            if (null ===$operationOwnsLocation) {
                $output->writeln(array(
                  '',
                  'For Operation modules only, specify whether the operation owns its location.', 
                  'State <comment>true</comment> or <comment>false</comment>.', 
                  ''              
                ));
                $question = new Question($questionHelper->getQuestion('Does the operation own its location?', 'true'), 'true');            
                $question->setValidator(function ($answer) {
                    return Validators::validateOperationOwnsLocation($answer, false);
                });
                $operationOwnsLocation = $questionHelper->ask($input, $output, $question);
                $input->setOption('operation-owns-location', $operationOwnsLocation);
            }  
        }

        /* channels value */
        if ($moduleType == 'activity') {
            $channelsForActivity = null;             
            try {
                $moduleDescription = $input->getOption('channels-for-activity') ? Validators::validateChannelsForActivity($input->getOption('channels-for-activity')) : null;
            } catch (\Exception $error) {
                $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
            }        
            if (null === $channelsForActivity) {
                $output->writeln(array(
                  '',
                  'For Activity modules only, specify the channels for the activity.', 
                  'List the channels as a comma-separated list.', 
                  ''              
                ));
                $question = new Question($questionHelper->getQuestion('Channels for the activity', $channelsForActivity), $channelsForActivity);            
                $question->setValidator(function ($answer) {
                    return Validators::validateChannelsForActivity($answer, false);
                });
                $channelsForActivity = $questionHelper->ask($input, $output, $question);
                $input->setOption('channels-for-activity', $channelsForActivity);
            }  
        }        

        /** author name **/
        $authorName = null;             
        try {
            $authorName = $input->getOption('author-name') ? Validators::validateAuthorName($input->getOption('author-name')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $authorName) {
            $output->writeln(array(
                ''
            ));
            $question = new Question($questionHelper->getQuestion('Author name', $authorName), $authorName);            
            $question->setValidator(function ($answer) {
                return Validators::validateAuthorName($answer, false);
            });
            $authorName = $questionHelper->ask($input, $output, $question);
            $input->setOption('author-name', $authorName);
        } 

        $derivedAuthorName = preg_replace('/\s+/', '', $authorName);
        
        /** author email address **/
        $authorEmail = null;             
        try {
            $authorEmail = $input->getOption('author-email') ? Validators::validateAuthorEmail($input->getOption('author-email')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $authorEmail) {
            $output->writeln(array(
                ''
            ));
            $question = new Question($questionHelper->getQuestion('Author\'s email address', $authorEmail), $authorEmail);            
            $question->setValidator(function ($answer) {
                return Validators::validateAuthorEmail($answer, false);
            });
            $authorEmail = $questionHelper->ask($input, $output, $question);
            $input->setOption('author-email', $authorEmail);
        }          

        /** module license **/
        $moduleLicense = null;             
        try {
            $moduleLicense = $input->getOption('module-license') ? Validators::validateModuleLicense($input->getOption('module-license')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $moduleLicense) {
            $output->writeln(array(
                '',
                'The module license defines how your module can be used by others.',
                'Example licenses include GPL, LGPL, MIT or Apache.',
                '',
            ));            
            $question = new Question($questionHelper->getQuestion('Module license', $moduleLicense), $moduleLicense);            
            $question->setValidator(function ($answer) {
                return Validators::validateModuleLicense($answer, false);
            });
            $moduleLicense = $questionHelper->ask($input, $output, $question);
            $input->setOption('module-license', $moduleLicense);
        }  
        
        /** bundle namespace **/
        $namespace = null;        
        try {
            $namespace = $input->getOption('namespace') ? Validators::validateBundleNamespace($input->getOption('namespace')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        $recommendedNamespace = ucfirst($derivedAuthorName) . '/CampaignChain/' . ucfirst($moduleType) . '/' . ucfirst($derivedPurpose) . 'Bundle';        
        if (null === $namespace) {
            $output->writeln(array(
                '',
                'Each bundle is hosted under a namespace'. 
                '(like <comment>Acme/CampaignChain/Channel/FacebookBundle</comment>).',
                'The namespace should begin with a "vendor" name like your company name',
                ' and it should end with the bundle name itself (which must have',
                '<comment>Bundle</comment> as a suffix).',
                '',
                'Based on the author name and module type, we suggest the namespace ',
                '<comment>'.$recommendedNamespace.'</comment>.',
                ''
            ));
            $question = new Question($questionHelper->getQuestion('Namespace',  $recommendedNamespace), $recommendedNamespace);
            $question->setValidator(function ($answer) {
                return Validators::validateBundleNamespace($answer, false);
            });
            $namespace = $questionHelper->ask($input, $output, $question);
            $input->setOption('namespace', $namespace);
        }
        
        
        /** bundle name **/
        $bundleName = null;       
        try {
            $bundleName = $input->getOption('bundle-name') ? Validators::validateBundleName($input->getOption('bundle-name')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        

        if (null === $bundleName) {        
            $recommendedBundleName = strtr($namespace, array('\\Bundle\\' => '', '\\' => ''));
            $output->writeln(array(
                '',
                'In your code, a bundle is often referenced by its name. It can be the',
                'concatenation of all namespace parts but it\'s really up to you to come',
                'up with a unique name (a good practice is to start with the vendor name).',
                '',
                'Based on the namespace, we suggest <comment>'.$recommendedBundleName.'</comment>.',
                '',
            ));
            
            $question = new Question($questionHelper->getQuestion('Bundle name', $recommendedBundleName), $recommendedBundleName);
            $question->setValidator(function ($answer) {
                 return Validators::validateBundleName($answer, false);
            });
            $bundleName = $questionHelper->ask($input, $output, $question);
            $input->setOption('bundle-name', $bundleName);            
        }  

        /** package name **/
        $packageName = null;       
        try {
            $packageName = $input->getOption('package-name') ? Validators::validatePackageName($input->getOption('package-name'), $input->getOption('module-type')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        $recommendedPackageName = strtolower($derivedAuthorName) . '/' . strtolower($moduleType) . '-' . strtolower($derivedPurpose);        

        if (null === $packageName) {        
            $output->writeln(array(
                '',
                'The Composer package name is the application name or vendor name, followed by',
                'a separating slash (/), then the module type followed by a dash and',
                'the bundle\'s purpose (like <comment>acme/channel-google</comment>).',
                '',
                'Based on the author name and module type, we suggest the package name ',
                '<comment>'.$recommendedPackageName.'</comment>.',
            ));
                    
            $question = new Question($questionHelper->getQuestion('Package name', $recommendedPackageName), $recommendedPackageName);
            $question->setValidator(function ($answer) use (&$moduleType) {
                 return Validators::validatePackageName($answer, $moduleType, false);
            });
            $packageName = $questionHelper->ask($input, $output, $question);
            $input->setOption('package-name', $packageName);
        }  
                
        /** bundle directory **/
        $dir = null;
        
        try {
            $dir = $input->getOption('dir') ? Validators::validateTargetDir($input->getOption('dir'), $bundleName, $namespace) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }
        
        if (null === $dir) {
            $dir = dirname($this->getContainer()->getParameter('kernel.root_dir')).'/src';
            $output->writeln(array(
                '',
                'The bundle can be generated anywhere. The suggested default directory uses',
                'the standard conventions.',
                ''
            ));
            $question = new Question($questionHelper->getQuestion('Target directory', $dir), $dir);
            $question->setValidator(function ($dir) use ($bundleName, $namespace) {
                return Validators::validateTargetDir($dir, $bundleName, $namespace);
            });
            $dir = $questionHelper->ask($input, $output, $question);
            $input->setOption('dir', $dir);
        }        
    }

    
    protected function setReminders(OutputInterface $output)
    {
        return array(
          '- Edit the <comment>composer.json</comment> file and register any other classes',
          '  or bundles that you need. Also add any other routes that you need there.',
          '',
          '- Edit the <comment>Resources/config/config.yml</comment> file and set',
          '  global configuration options for your module.',
          '',
        );
    }

    protected function createGenerator()
    {
        return new ModuleGenerator($this->getContainer()->get('filesystem'));
    }

    
}

