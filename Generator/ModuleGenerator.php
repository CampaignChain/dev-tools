<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
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
    
    public function generateConf($namespace, $bundleName, $dir, $moduleType, $moduleIdentifier, $moduleDescription, $moduleLicense, $authorName, $authorEmail, $packageName, $operationOwnsLocation, $channelsForActivity, $routing)
    {
    
        $dir .= '/'.strtr($namespace, '\\', '/');
        $namespace = str_replace("\\", "\\\\", $namespace);
        $this->setSkeletonDirs(__DIR__ . '/../Resources/skeleton');
        $parameters = array(
            'namespace' => $namespace,
            'bundle_name'  => $bundleName,
            'module_type' => $moduleType, 
            'module_id' => $moduleIdentifier, 
            'module_description' => $moduleDescription, 
            'module_license' => $moduleLicense, 
            'author_name' => $authorName, 
            'author_email' => $authorEmail,
            'package_name' => $packageName, 
            'package_description' => $moduleDescription,
            'owns_location' => $operationOwnsLocation,
            'channels_for_activity' => explode(',', $channelsForActivity), 
            'gen_routing' => $routing
        );
        
        if ($routing == 'yes') {
            $parameters['route_prefix'] = str_replace(array('/', '-'), '_', $packageName);
        }
        
        $this->renderFile('campaignchain.yml.twig', $dir.'/campaignchain.yml', $parameters);
        $this->renderFile('composer.json.twig', $dir.'/composer.json', $parameters);
        $this->renderFile('config.yml.twig', $dir.'/Resources/config/config.yml', $parameters);
        if ($routing == 'yes') {
            // overwrite the default routing.yml file created by the Symfony generator
            $this->renderFile('routing.yml.twig', $dir.'/Resources/config/routing.yml', $parameters);
        } else {
            // remove the default routing.yml file created by the Symfony generator
            $this->filesystem->remove($dir.'/Resources/config/routing.yml');
        }
    }    
}

