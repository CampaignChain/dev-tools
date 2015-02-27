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
              new InputOption('vendor-name', '', InputOption::VALUE_REQUIRED, 'The vendor name'),
              new InputOption('author-name', '', InputOption::VALUE_REQUIRED, 'The author name'),
              new InputOption('author-email', '', InputOption::VALUE_OPTIONAL, 'The author email address'),
              new InputOption('package-license', '', InputOption::VALUE_REQUIRED, 'The package license'),
              new InputOption('package-website', '', InputOption::VALUE_REQUIRED, 'The package website URL'),
              new InputOption('package-description', '', InputOption::VALUE_OPTIONAL, 'The package description'),
              new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The bundle namespace'),
              new InputOption('bundle-name', '', InputOption::VALUE_REQUIRED, 'The bundle name'),
              new InputOption('package-name', '', InputOption::VALUE_REQUIRED, 'The Composer package name'),
              new InputOption('dir', '', InputOption::VALUE_REQUIRED, 'The bundle directory'),
              new InputOption('gen-routing', '', InputOption::VALUE_REQUIRED, 'Whether to generate a routing.yml file'),
              new InputOption('modules', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The modules to be added'),
              new InputOption('more-modules', '', InputOption::VALUE_REQUIRED, 'Whether to add more modules (non-interactive mode only)')))
            ->setName('campaignchain:generate:module')
            ->setDescription('Generates new CampaignChain module skeletons as a bundle');
        
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

        foreach (array('module-type', 'vendor-name', 'modules', 'author-name', 'package-license', 'package-website', 'bundle-name', 'namespace', 'package-name', 'dir', 'gen-routing') as $option) {
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
        $vendorName = Validators::validateVendorName($input->getOption('vendor-name'), false);
        $authorName = Validators::validateAuthorName($input->getOption('author-name'), false);
        $authorEmail = Validators::validateAuthorEmail($input->getOption('author-email'), false);
        $packageLicense = Validators::validatePackageLicense($input->getOption('package-license'), false);
        $packageWebsite = Validators::validatePackageWebsiteUrl($input->getOption('package-website'), false);
        $packageDescription = Validators::validateDescription($input->getOption('package-description'), false);
        $packageName = Validators::validatePackageName($input->getOption('package-name'), false);
        $routing = Validators::validateBooleanAnswer($input->getOption('gen-routing'), false);
        $modules = $this->parseModules($input->getOption('modules'), $moduleType);
        
        $questionHelper->writeSection($output, 'Bundle generation');

        if (!$this->getContainer()->get('filesystem')->isAbsolutePath($dir)) {
            $dir = getcwd().'/'.$dir;
        }

        $generator = $this->getGenerator();
        
        $generator->generate($namespace, $bundleName, $dir, $format, $structure);
        $output->writeln('Generating the bundle: <info>OK</info>');     
        
        $generator->generateConf($namespace, $bundleName, $dir, $moduleType, $modules, $packageLicense, $packageWebsite, $packageDescription, $vendorName, $authorName, $authorEmail, $packageName, $routing);
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
        $runner($this->setReminders($output, $moduleType));
        
        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the CampaignChain module generator');
        
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
              '* \'operation\', similar to the Activity module type.',
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

        /** vendor name **/
        $vendorName = null;             
        try {
            $vendorName = $input->getOption('vendor-name') ? Validators::validateVendorName($input->getOption('vendor-name')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $vendorName) {
            $output->writeln(array(
                '',
                'Every module has a unique identifier, which by convention follows the format ',
                '[vendorName]-[moduleName]-[moduleSuffix]. The next three questions will',
                'ask for these inputs to generate the module identifier.',
                '',
                'Please provide the vendor name as a single word.',
                ''
            ));
            $question = new Question($questionHelper->getQuestion('Vendor name', $vendorName), $vendorName);            
            $question->setValidator(function ($answer) {
                return Validators::validateVendorName($answer, false);
            });
            $vendorName = $questionHelper->ask($input, $output, $question);
            $input->setOption('vendor-name', $vendorName);
        } 

        $derivedVendorName = preg_replace('/\s+/', '', $vendorName);
            
        // modules
        $input->setOption('modules', $this->addModules($input, $output, $questionHelper, $moduleType, $vendorName));
        $modules = $input->getOption('modules');

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

        /** package license **/
        $packageLicense = null;             
        try {
            $packageLicense = $input->getOption('package-license') ? Validators::validatePackageLicense($input->getOption('package-license')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $packageLicense) {
            $output->writeln(array(
                '',
                'The package license defines how your module(s) can be used by others.',
                'Please use a license identifier as specified by the SPDX License List at',
                'http://spdx.org/licenses/. For example: <comment>GPL-3.0+</comment>, <comment>Apache-2.0</comment>',
                '',
            ));            
            $question = new Question($questionHelper->getQuestion('Package license', $packageLicense), $packageLicense);            
            $question->setValidator(function ($answer) {
                return Validators::validatePackageLicense($answer, false);
            });
            $packageLicense = $questionHelper->ask($input, $output, $question);
            $input->setOption('package-license', $packageLicense);
        }  


        /** package description **/
        $packageDescription = null;             
        try {
            $packageDescription = $input->getOption('package-description') ? Validators::validateDescription($input->getOption('package-description')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $packageDescription) {
            $output->writeln(array(
                '',
                'The package description (optional) briefly explains the purpose of the package', 
                '(like <comment>Collection of Twitter-related modules</comment>).',
                '',
            ));            
            $question = new Question($questionHelper->getQuestion('Package description', $packageDescription), $packageDescription);            
            $question->setValidator(function ($answer) {
                return Validators::validateDescription($answer, false);
            });
            $packageDescription = $questionHelper->ask($input, $output, $question);
            $input->setOption('package-description', $packageDescription);
        }  
        
        /** package website **/
        $packageWebsite = null;             
        try {
            $packageWebsite = $input->getOption('package-website') ? Validators::validatePackageWebsiteUrl($input->getOption('package-website')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }        
        if (null === $packageWebsite) {
            $output->writeln(array(
                '',
                'Specify the website URL for your package.',
                '',
            ));            
            $question = new Question($questionHelper->getQuestion('Package website URL', $packageWebsite), $packageWebsite);            
            $question->setValidator(function ($answer) {
                return Validators::validatePackageWebsiteUrl($answer, false);
            });
            $packageWebsite = $questionHelper->ask($input, $output, $question);
            $input->setOption('package-website', $packageWebsite);
        }         
        
        /** bundle namespace **/
        $namespace = null;        
        try {
            $namespace = $input->getOption('namespace') ? Validators::validateBundleNamespace($input->getOption('namespace')) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }
        
        $derivedModuleName = implode('', array_map('ucwords', explode('-', $modules[0]['module_name'])));
        $recommendedNamespace = ucfirst($derivedVendorName) . '/' . ucfirst($moduleType) . '/' . $derivedModuleName . 'Bundle';
        if (null === $namespace) {
            $output->writeln(array(
                '',
                'Each bundle is hosted under a namespace'. 
                '(like <comment>Acme/CampaignChain/Channel/FacebookBundle</comment>).',
                '',
                'The namespace should begin with a "vendor" name like your company name',
                ' and it should end with the bundle name itself (which must have',
                '<comment>Bundle</comment> as a suffix).',
                '',
                'Based on the vendor name and module type, we suggest the namespace ',
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
        $recommendedPackageName = strtolower($derivedVendorName) . '/' . strtolower($moduleType) . '-' . strtolower($modules[0]['module_name']);        

        if (null === $packageName) {        
            $output->writeln(array(
                '',
                'The Composer package name is the application name or vendor name, followed by',
                'a separating slash (/), then the module type followed by a dash and',
                'the bundle\'s purpose (like <comment>acme/channel-google</comment>).',
                '',
                'Based on the vendor name and module type, we suggest the package name ',
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

        /** routing.yml file **/
        $routing = null;
        
        try {
            $routing = $input->getOption('gen-routing') ? Validators::validateBooleanAnswer($input->getOption('gen-routing'), $bundleName, $namespace) : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }
        
        if (null === $routing) {
            $output->writeln(array(
                ''
            ));
            $question = new Question($questionHelper->getQuestion('Do you want to generate a <comment>routing.yml</comment> file?', 'yes'), 'yes');
            $question->setValidator(function ($answer) {
                return Validators::validateBooleanAnswer($answer, false);
            });
            $routing = $questionHelper->ask($input, $output, $question);
            $input->setOption('gen-routing', $routing);
        }          
    }

    
    protected function setReminders(OutputInterface $output, $moduleType)
    {
        $messages = array(
          '- Edit the <comment>composer.json</comment> file and register any other classes',
          '  or bundles that you need.',
          '',
          '- Edit the <comment>Resources/config/routing.yml</comment> file and add routes',
          '  for your module.',
          '',
          '- Edit the <comment>Resources/config/config.yml</comment> file and set',
          '  global configuration options for your module.',
          '',
        );
        if (strtolower($moduleType) == 'activity' || strtolower($moduleType) == 'channel') {
          $messages = array_merge($messages, array(
            '- Edit the <comment>campaignchain.yml</comment> file and verify the routes for your module.',
            '',
          ));
        }
        if (strtolower($moduleType) == 'location' || strtolower($moduleType) == 'activity' || strtolower($moduleType) == 'channel') {
          $messages = array_merge($messages, array(
            '- Edit the <comment>campaignchain.yml</comment> file and add hooks used by your module.',
            '',
          ));
        }
        if (strtolower($moduleType) == 'operation') {
          $messages = array_merge($messages, array(
            '- Edit the <comment>campaignchain.yml</comment> file and add metrics for your module.',
            '',
          ));
          $messages = array_merge($messages, array(
            '- Edit the <comment>campaignchain.yml</comment> file and add services used by your module.',
            '',
          ));
        }
        return $messages;
    }
    
    public function addModules(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper, $moduleType, $vendorName)
    {
        $modules = $this->parseModules($input->getOption('modules'), $moduleType);
        
        if (is_null($input->getOption('more-modules'))) {
            $input->setOption('more-modules', 'yes');
        }
        while ($input->getOption('more-modules') == 'yes') {
            /** module name **/
            if (count($modules) == 0) {
              $moduleName = null;
              $output->writeln(array(
                '',
                'The minimum syntax of the module name is a single word,',
                'with additional optional words separated by hyphens, indicating the ',
                'channel that the module relates to (like <comment>twitter</comment> or <comment>analytics-cta</comment>).',
                '',    
                'The first module name entered will also be used to generate',
                'the default bundle and package name. You will have the opportunity to',
                'change this later.',
                ''              
              ));
              $question = new Question($questionHelper->getQuestion('Module name', $moduleName), $moduleName);            
              $question->setValidator(function ($answer) {
                  return Validators::validateModuleName($answer, false);
              });
              $moduleName = $questionHelper->ask($input, $output, $question);
              if (!$moduleName) {
                  $output->writeln(array(
                    '<comment>You must define at least one module.</comment>',
                  ));
                  continue;
              }
            } else {
                $moduleName = $modules[0]['module_name'];                 
            }
            
            /** module name suffix **/
            $moduleNameSuffix = null;             
            $output->writeln(array(
              '',
              'To further describe your module, add an optional suffix.',
              'The recommended syntax of the module suffix is to use hyphens (-)',
              'to separate words (like <comment>update-status</comment>).',
              ''              
            ));
            $question = new Question($questionHelper->getQuestion('Module name suffix', $moduleNameSuffix), $moduleNameSuffix);            
            if (count($modules) == 0) {
                $question->setValidator(function ($answer) {
                    return Validators::validateModuleNameSuffix($answer, false);
                });                        
            } else {
                $question->setValidator(function ($answer) use ($modules) {
                    return Validators::validateModuleNameSuffix($answer, $modules, true, false);
                });            
            }

            $moduleNameSuffix = $questionHelper->ask($input, $output, $question);
            
            $output->writeln(array(
              '',
              'Based on your inputs, the module identifier for your module will be.',
              '<comment>' . $this->createModuleIdentifier($vendorName, $moduleName, $moduleNameSuffix) . '</comment>).',
              ''              
            ));

            /** module display name **/
            $moduleDisplayName = null;             
            $output->writeln(array(
              '',
              'The module display name is a human-readable label that will be shown', 
              'in CampaignChain\'s graphical user interface',
              '(like <comment>Update Twitter Status</comment>).',
              ''              
            ));
            $question = new Question($questionHelper->getQuestion('Module display name', $moduleDisplayName), $moduleDisplayName);            
            $question->setValidator(function ($answer) {
                return Validators::validateDisplayName($answer, false);
            });
            $moduleDisplayName = $questionHelper->ask($input, $output, $question);
        
            /** module description **/
            $moduleDescription = null;             
            $output->writeln(array(
              '',
              'The module description (optional) briefly explains the purpose of the module', 
              '(like <comment>This module updates your Twitter status</comment>).',
              ''              
            ));
            $question = new Question($questionHelper->getQuestion('Module description', $moduleDescription), $moduleDescription);            
            $question->setValidator(function ($answer) {
                return Validators::validateDescription($answer, false);
            });
            $moduleDescription = $questionHelper->ask($input, $output, $question);
            
            /** owns_location value **/
            $operationOwnsLocation = null;             
            if ($moduleType == 'operation') {
                $output->writeln(array(
                  '',
                  'For Operation modules only, specify whether the operation owns its location.',
                  'State <comment>true</comment> or <comment>false</comment>.',
                  'For example, choose <comment>true</comment> if the operation creates a location, such as when posting on Twitter.',
                  ''
                ));
                $question = new Question($questionHelper->getQuestion('Does the operation own its location?', 'true'), 'true');            
                $question->setValidator(function ($answer) {
                    return Validators::validateOperationOwnsLocation($answer, false);
                });
                $operationOwnsLocation = $questionHelper->ask($input, $output, $question);
            }

            /* metrics value */
            $metricsForOperation = null;             
            if ($moduleType == 'operation') {
                $output->writeln(array(
                  '',
                  'For Operation modules only, specify the metrics for the operation',
                  '(like <comment>Clicks</comment> or <comment>Clicks, Likes, Shares</comment>).',
                  '',
                  'List the metrics as a comma-separated list.', 
                  ''              
                ));
                $question = new Question($questionHelper->getQuestion('Metrics for the operation', $metricsForOperation), $metricsForOperation);            
                $question->setValidator(function ($answer) {
                    return Validators::validateMetricsForOperation($answer, false);
                });
                $metricsForOperation = $questionHelper->ask($input, $output, $question);
            }  
        
            /* channels value */
            $channelsForActivity = null;             
            if ($moduleType == 'activity') {
                $output->writeln(array(
                  '',
                  'For Activity modules only, specify the channels for the activity.',
                  '',
                  'The format for each channel module name is', 
                  '<comment>vendor/channel-[module-name]/[module-id]</comment>', 
                  '(like <comment>acme/channel-twitter/acme-twitter</comment>).',
                  '',
                  'List the channels as a comma-separated list.', 
                  ''              
                ));
                $question = new Question($questionHelper->getQuestion('Channels for the activity', $channelsForActivity), $channelsForActivity);            
                $question->setValidator(function ($answer) {
                    return Validators::validateChannelsForActivity($answer, false);
                });
                $channelsForActivity = $questionHelper->ask($input, $output, $question);
            }  

            /* hooks value */
            $hooksForActivity = null;                        
            if ($moduleType == 'activity') {
                $output->writeln(array(
                  '',
                  'For Activity modules only, specify the hooks for the activity.',
                  '',
                  'The format for each hook name is <comment>[module-name]</comment>', 
                  '(like <comment>campaignchain-assignee</comment>).',
                  '',
                  'List the hooks as a comma-separated list.', 
                  ''              
                ));
                $question = new Question($questionHelper->getQuestion('Hooks for the activity', $hooksForActivity), $hooksForActivity);            
                $question->setValidator(function ($answer) {
                    return Validators::validateHooksForActivity($answer, false);
                });
                $hooksForActivity = $questionHelper->ask($input, $output, $question);
            }  
            
            $derivedClassName = implode('', array_map('ucwords', explode('-', $moduleName . '-' . $moduleNameSuffix)));
            
            $modules[] = array(
                'module_name'             => $moduleName,
                'module_name_suffix'      => $moduleNameSuffix,
                'module_display_name'     => $moduleDisplayName,
                'module_description'      => $moduleDescription,
                'operation_owns_location' => $operationOwnsLocation,
                'channels_for_activity'   => explode(',', $channelsForActivity), 
                'hooks_for_activity'      => !empty($hooksForActivity) ? explode(',', $hooksForActivity) : null, 
                'metrics_for_operation'   => !empty($metricsForOperation) ? explode(',', $metricsForOperation) : null,
                'class_name'              => $derivedClassName
            );
            
            $output->writeln(array(
              '',
            ));
            $question = new Question($questionHelper->getQuestion('Add another module?', 'no'), 'no');            
            $question->setValidator(function ($answer) {
                return Validators::validateBooleanAnswer($answer, false);
            });
            $anotherModule = $questionHelper->ask($input, $output, $question);
            $input->setOption('more-modules', $anotherModule);
            
        }            
        return $modules;
        
    }

    public function parseModules($modules, $moduleType)
    {
        if (is_array($modules)) {
            if (isset($modules[0]['module_name'])) {
              return $modules;
            }
        }

        $newModules = array();

        foreach ($modules as $module) {
            $data = explode(':', $module);

            if (count($newModules) == 0) {
                $moduleName = Validators::validateModuleName($data[0], false);
                $moduleNameSuffix = Validators::validateModuleNameSuffix($data[1], false);
            } else {
                $moduleName = $newModules[0]['module_name'];
                $moduleNameSuffix = Validators::validateModuleNameSuffix($data[1], $newModules, true, false);
            }
            $moduleDisplayName = Validators::validateDisplayName($data[2], false);
            $moduleDescription = Validators::validateDescription($data[3], false);
            $derivedClassName = implode('', array_map('ucwords', explode('-', $moduleName . '-' . $moduleNameSuffix)));
            $operationOwnsLocation = null;
            $channelsForActivity = null;
            if ($moduleType == 'operation') {
                $operationOwnsLocation = Validators::validateOperationOwnsLocation($data[4], false);
                $metricsForOperation = Validators::validateMetricsForOperation($data[5], false);
            }
            if ($moduleType == 'activity') {
                $channelsForActivity = Validators::validateChannelsForActivity($data[4], false);
                $hooksForActivity = Validators::validateHooksForActivity($data[5], false);
            }

            $newModules[] = array(
                'module_name'               => $moduleName,
                'module_name_suffix'        => $moduleNameSuffix,
                'module_display_name'       => $moduleDisplayName,
                'module_description'        => $moduleDescription,
                'operation_owns_location'   => $operationOwnsLocation,
                'channels_for_activity'     => explode(',', $channelsForActivity), 
                'hooks_for_activity'        => !empty($hooksForActivity) ? explode(',', $hooksForActivity) : null, 
                'metrics_for_operation'     => !empty($metricsForOperation) ? explode(',', $metricsForOperation) : null,
                'class_name'                => $derivedClassName
            );
        }
        return $newModules;
    }    
    
    protected function createGenerator()
    {
        return new ModuleGenerator($this->getContainer()->get('filesystem'));
    }

    protected function createModuleIdentifier($vendorName, $moduleName, $moduleNameSuffix = '')
    {
        $moduleIdentifier = strtolower($vendorName) . '-' . strtolower($moduleName);
        if (!empty($moduleNameSuffix)) {
            $moduleIdentifier .= '-' . strtolower($moduleNameSuffix);
        }
        return $moduleIdentifier;
    }
}

