<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\GeneratorBundle\Command;

use Sensio\Bundle\GeneratorBundle\Command\Validators as SensioValidators;

class Validators extends SensioValidators
{

    public static function validateModuleType($type)
    {
        $type = strtolower($type);

        if (!in_array($type, array('channel', 'location', 'activity', 'operation', 'campaign', 'milestone', 'report', 'security', 'distribution'))) {
            throw new \RuntimeException(sprintf('Type "%s" is not supported.', $type));
        }

        return $type;
    }

    /*
    public static function validateModuleIdentifier($id)
    {
        // validate characters
        if (!preg_match('/^(?:[a-zA-Z0-9_-]*-?)+$/', $id)) {
            throw new \InvalidArgumentException('The module identifier contains invalid characters.');
        }
        return $id;
    }
    */

    public static function validateModuleName($name)
    {
        // validate characters
        if (!preg_match('/^(?:[a-zA-Z0-9_-]*-?)+$/', $name)) {
            throw new \InvalidArgumentException('The module name contains invalid characters.');
        }
        return $name;
    }
    
    public static function validateModuleCount($modules)
    {
        if (count($modules) == 0) {
            throw new \InvalidArgumentException('At least one module must be defined.');
        }
        return true;
    }
    

    public static function validateModuleNameSuffix($suffix, $modules = array(), $required = false)
    {
        // validate characters
        if ($required) {
          if (!preg_match('/^[a-zA-Z0-9_-]+$/', $suffix)) {
              throw new \InvalidArgumentException('The module name suffix contains invalid characters.');
          }
          foreach ($modules as $module) {
              if ($module['module_name_suffix'] == $suffix){
                  throw new \InvalidArgumentException('The module name suffix is already in use.');
                  break;                  
              }
          }
        } else {
          if (!preg_match('/^(?:[a-zA-Z0-9_-]*-?)+$/', $suffix)) {
              throw new \InvalidArgumentException('The module name suffix contains invalid characters.');
          }        
        }
        return $suffix;
    }
    
    public static function validatePackageName($package, $moduleType)
    {
        $package = strtr($package, '/', '\\');
        
        // validate characters
        if (!preg_match('/^(?:[a-zA-Z0-9_-\x7f-\xff]*\\\?)+$/', $package)) {
            throw new \InvalidArgumentException('The package name contains invalid characters.');
        }

        // validate reserved keywords
        $reserved = self::getReservedWords();
        foreach (explode('\\', $package) as $word) {
            if (in_array(strtolower($word), $reserved)) {
                throw new \InvalidArgumentException(sprintf('The package name cannot contain PHP reserved words ("%s").', $word));
            }
        }

        // validate that the package includes a vendor name
        if (false === strpos($package, '\\')) {
            $msg = array();
            $msg[] = sprintf('The package name must contain a vendor name (e.g. "vendor\%s" instead of simply "%s").', $package, $package);
            throw new \InvalidArgumentException(implode("\n\n", $msg));
        }

        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\?)+(' . $moduleType . ')(-(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*?))+$/', $package)) {
            $msg = array();
            $data = explode('\\', $package);
            $msg[] = sprintf('The package name must include the specified module type \'' . $moduleType . '\' after the vendor name (e.g. "vendor/%s-%s" instead of simply "%s").', $moduleType, $data[1], $data[1]);
            throw new \InvalidArgumentException(implode("\n\n", $msg));
        }
        $package = strtr($package, '\\', '/');
        return $package;
    }

    public static function validateDisplayName($name)
    {
        if(!preg_match("/^[a-zA-Z0-9.\-\_\s]+$/", $name)) {
            throw new \InvalidArgumentException('The display name contains invalid characters.');
        }
        return $name;
    }

    public static function validateDescription($description)
    {
        if(!preg_match("/^(?:[a-zA-Z0-9.,\-\_\s]?)+$/", $description)) {
            throw new \InvalidArgumentException('The description contains invalid characters.');
        }
        return $description;
    }
    
    public static function validateAuthorName($name)
    {
        if(!preg_match("/^[a-zA-Z0-9.,\-\_\s]+$/", $name)) {
            throw new \InvalidArgumentException('The author name contains invalid characters.');
        }
        return $name;
    }

    public static function validateVendorName($name)
    {
        if(!preg_match("/^[a-zA-Z0-9_]+$/", $name)) {
            throw new \InvalidArgumentException('The vendor name contains invalid characters.');
        }
        return $name;
    }
    
    public static function validateAuthorEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('The email address is invalid');
        }
        return $email;
    }

    public static function validatePackageWebsiteUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('The URL is invalid');
        }
        return $url;
    }
    
    public static function validatePackageLicense($license)
    {
        if(!preg_match("/^[a-zA-Z0-9.\-\_\s]+$/", $license)) {
            throw new \InvalidArgumentException('The license contains invalid characters.');
        }
        return $license;
    }

    public static function validateOperationOwnsLocation($value)
    {
        if (!in_array($value, array('true', 'false'))) {
            throw new \InvalidArgumentException('Only \'true\' and \'false\' values are supported');
        }
        return $value;
    }

    public static function validateChannelsForActivity($value)      
    {
        // allow
        // vendor/channel-module/module-name
        // vendor/channel-module-name/module-name
        // vendor/channel-module-name/channel-module-name
        foreach (explode(',', $value) as $c) {
          $c = trim(strtr($c, '/', '\\'));
          if (!preg_match('/^(?:[a-zA-Z][a-zA-Z0-9_]*\\\?)+(channel)(-(?:[a-zA-Z][a-zA-Z0-9_]*\\\?))+([a-zA-Z][a-zA-Z0-9_]*)(-(?:[a-zA-Z][a-zA-Z0-9_]*?))+$/', $c)) {
              throw new \InvalidArgumentException('At least one of the channel package names does not seem to be valid, each channel package name should be in the format "[vendor-name]/channel-[module-name]/[channel-module-name]".'); 
          }        
        }
        return $value;
    }

    public static function validateHooksForActivity($value)      
    {
        // allow
        // channel-module
        foreach (explode(',', $value) as $c) {
          $c = trim($c);
          if (!preg_match('/^(?:[a-z0-9_-]*-?)+$/', $c)) {
              throw new \InvalidArgumentException('At least one of the hook names does not seem to be valid, each hook name should be in the format "[module-name]".'); 
          }        
        }
        return $value;
    }

    public static function validateLocationParameterName($value)
    {
        // allow
        // campaignchain.location.[vendor name].[module name]
        // campaignchain.location.[vendor name].[module name].[module suffix]
        foreach (explode(',', $value) as $c) {
            $c = trim(strtr($c, '/', '\\'));
            if (!preg_match('/^(campaignchain.location.)([a-zA-Z][a-zA-Z0-9_]*)+(.([a-zA-Z][a-zA-Z0-9_]*))+(.(?:[a-zA-Z][a-zA-Z0-9_]*?))+$/', $c)) {
                throw new \InvalidArgumentException('The Location parameter name is not valid. The format should be "campaignchain.location.[vendor name].[module name].[optional module-suffix]".');
            }
        }
        return $value;
    }

    public static function validateEqualsOperation($value)
    {
        if (!in_array($value, array('true', 'false'))) {
            throw new \InvalidArgumentException('Only \'true\' and \'false\' values are supported');
        }
        return $value;
    }

    public static function validateOperationParameterNames($value)
    {
        // allow
        // campaignchain.operation.[vendor name].[module name]
        // campaignchain.operation.[vendor name].[module name].[module suffix]
        foreach (explode(',', $value) as $c) {
            $c = trim(strtr($c, '/', '\\'));
            if (!preg_match('/^(campaignchain.operation.)([a-zA-Z][a-zA-Z0-9_]*)+(.([a-zA-Z][a-zA-Z0-9_]*))+(.(?:[a-zA-Z][a-zA-Z0-9_]*?))+$/', $c)) {
                throw new \InvalidArgumentException('At least one of the Operation parameter names does not seem to be valid. Each of them should be in the format "campaignchain.operation.[vendor-name].[module-name].[optional module-suffix]".');
            }
        }
        return $value;
    }

    public static function validateMetricsForOperation($value)      
    {
        foreach (explode(',', $value) as $c) {
          $c = trim($c);
          if (!preg_match('/^(?:[a-zA-Z0-9_]*?)+$/', $c)) {
              throw new \InvalidArgumentException('At least one of the metric names does not seem to be valid, use alphanumeric characters and underscores only.'); 
          }        
        }
        return $value;
    }  
    
    public static function validateBooleanAnswer($type)
    {
        $type = strtolower($type);

        if (!in_array($type, array('yes', 'no'))) {
            throw new \RuntimeException(sprintf('Answer "%s" is not supported.', $type));
        }

        return $type;
    }    
}