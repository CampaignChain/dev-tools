<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\GeneratorBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\Container;
use Sensio\Bundle\GeneratorBundle\Generator\BundleGenerator;

class ModuleGenerator extends BundleGenerator
{

    private $filesystem;
    
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        return parent::__construct($filesystem);
    }

    public function generate($namespace, $bundleName, $dir, $format, $structure)
    {
        return parent::generate($namespace, $bundleName, $dir, $format, $structure);
    }
    
    public function generateConf($namespace, $bundleName, $dir, $moduleType, $modules, $packageLicense, $packageWebsite, $packageDescription, $vendorName, $authorName, $authorEmail, $packageName, $routing)
    {
    
        $dir .= '/'.strtr($namespace, '\\', '/');
        $namespace = str_replace("\\", "\\\\", $namespace);
        $this->setSkeletonDirs(__DIR__ . '/../Resources/skeleton');
        $parameters = array(
            'namespace' => $namespace,
            'bundle_name'  => $bundleName,
            'module_type' => $moduleType, 
            'modules' => $modules,
            'package_license' => $packageLicense, 
            'package_website' => $packageWebsite, 
            'vendor_name' => $vendorName, 
            'author_name' => $authorName, 
            'author_email' => $authorEmail,
            'package_name' => $packageName, 
            'package_description' => $packageDescription,
            'gen_routing' => $routing
        );
        
        if ($routing == 'yes') {
            $parameters['route_prefix'] = str_replace(array('/', '-'), '_', $packageName);
        }

        // per-module files
        foreach ($modules as $module) {        
            $derivedClassName = $module['class_name'];
            if (strtolower($moduleType) == 'operation') {
                $this->renderFile('job/Job.php.twig', $dir.'/Job/Job.php', array_merge($parameters, $module));
                $this->renderFile('job/Report.php.twig', $dir.'/Job/' . $derivedClassName . 'Report.php', array_merge($parameters, $module));        
                $this->renderFile('form/OperationType.php.twig', $dir.'/Form/Type/' . $derivedClassName . 'OperationType.php', array_merge($parameters, $module));        
                $this->renderFile('entity/Entity.php.twig', $dir.'/Entity/' . $derivedClassName . '.php', array_merge($parameters, $module));
                $this->renderFile('views/read.html.twig', $dir.'/Resources/views/read' . ((!empty($module['module_name_suffix'])) ? '_' . strtolower(str_replace('-', '_', $module['module_name_suffix'])) : '') . '.html.twig', array_merge($parameters, $module));        
                $this->renderFile('public/css/base.css.twig', $dir.'/Resources/public/css/' . $module['module_name_underscore'] . ((!empty($module['module_name_suffix'])) ? '_' . strtolower(str_replace('-', '_', $module['module_name_suffix'])) : '') . '.css', array_merge($parameters, $module));
            }
            if (strtolower($moduleType) == 'activity') {
                $this->renderFile('controller/ActivityHandler.php.twig', $dir.'/Controller/' . $derivedClassName . 'Handler.php', array_merge($parameters, $module));
            }
            if (strtolower($moduleType) == 'campaign') {
                $this->renderFile('job/Job.php.twig', $dir.'/Service/Job.php', array_merge($parameters, $module));
            }
            if (strtolower($moduleType) == 'channel') {
                $this->renderFile('controller/ChannelController.php.twig', $dir.'/Controller/' . $derivedClassName . 'Controller.php', array_merge($parameters, $module));
            } 
            if (strtolower($moduleType) == 'report') {
                $this->renderFile('controller/ReportController.php.twig', $dir.'/Controller/' . $derivedClassName . 'Controller.php', array_merge($parameters, $module));
            }                
        }

        // per-bundle files
        if (strtolower($moduleType) == 'activity') {
            // overwrite the default services.yml file created by the Symfony generator
            $this->renderFile('config/activity_services.yml.twig', $dir.'/Resources/config/services.yml', $parameters);
        }
        if (strtolower($moduleType) == 'location') {
            // overwrite the default services.yml file created by the Symfony generator
            $this->renderFile('config/location_services.yml.twig', $dir.'/Resources/config/services.yml', $parameters);
        }
        if (strtolower($moduleType) == 'operation') {
            // overwrite the default services.yml file created by the Symfony generator
            $this->renderFile('config/operation_services.yml.twig', $dir.'/Resources/config/services.yml', $parameters);
            $this->renderFile('views/fields.html.twig', $dir.'/Resources/views/Form/fields.html.twig', $parameters);        
        }
        if (strtolower($moduleType) == 'campaign') {
            // overwrite the default services.yml file created by the Symfony generator
            $this->renderFile('config/campaign_services.yml.twig', $dir.'/Resources/config/services.yml', $parameters);
        }

        $this->renderFile('config/campaignchain.yml.twig', $dir.'/campaignchain.yml', $parameters);
        $this->renderFile('config/composer.json.twig', $dir.'/composer.json', $parameters);
        $this->renderFile('config/config.yml.twig', $dir.'/Resources/config/config.yml', $parameters);
        if ($routing == 'yes') {
            // overwrite the default routing.yml file created by the Symfony generator
            $this->renderFile('config/routing.yml.twig', $dir.'/Resources/config/routing.yml', $parameters);
        } else {
            // remove the default routing.yml file created by the Symfony generator
            $this->filesystem->remove($dir.'/Resources/config/routing.yml');
        }
    }    
}

