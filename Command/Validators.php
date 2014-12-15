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

class Validators
{

    public static function validateBundleName($bundle)
    {
        $bundle = strtr($bundle, '/', '\\');
        
        // validate characters
        if (!preg_match('/^(?:[a-zA-Z0-9_-\x7f-\xff]*\\\?)+$/', $bundle)) {
            throw new \InvalidArgumentException('The bundle name contains invalid characters.');
        }

        // validate reserved keywords
        $reserved = self::getReservedWords();
        foreach (explode('\\', $bundle) as $word) {
            if (in_array(strtolower($word), $reserved)) {
                throw new \InvalidArgumentException(sprintf('The bundle name cannot contain PHP reserved words ("%s").', $word));
            }
        }

        // validate that the bundle includes a vendor name
        if (false === strpos($bundle, '\\')) {
            $msg = array();
            $msg[] = sprintf('The bundle name must contain a vendor name (e.g. "vendor\%s" instead of simply "%s").', $bundle, $bundle);
            throw new \InvalidArgumentException(implode("\n\n", $msg));
        }
        
        // validate that the bundle includes only a single hyphen
        $segs = explode('-', $bundle);
        if (count($segs) > 2) {
            throw new \InvalidArgumentException(sprintf('The bundle name cannot contain more than one hyphen.'));        
        }

        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\?)+(channel|location|activity|operation)-(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*?)$/', $bundle)) {
            $msg = array();
            $data = explode('\\', $bundle);
            $msg[] = sprintf('The bundle name must include one of the pre-defined types \'channel\', \'location\', \'activity\' or \'operation\' after the vendor name (e.g. "vendor\channel-%s" instead of simply "%s").', $data[1], $data[1]);
            throw new \InvalidArgumentException(implode("\n\n", $msg));
        }
        $bundle = strtr($bundle, '\\', '/');
        return $bundle;
    }

    public static function validateBundleNamespace($namespace, $requireVendorNamespace = true)
    {
        if (!preg_match('/Bundle$/', $namespace)) {
            throw new \InvalidArgumentException('The namespace must end with Bundle.');
        }

        $namespace = strtr($namespace, '/', '\\');
        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\?)+$/', $namespace)) {
            throw new \InvalidArgumentException('The namespace contains invalid characters.');
        }

        // validate reserved keywords
        $reserved = self::getReservedWords();
        foreach (explode('\\', $namespace) as $word) {
            if (in_array(strtolower($word), $reserved)) {
                throw new \InvalidArgumentException(sprintf('The namespace cannot contain PHP reserved words ("%s").', $word));
            }
        }

        // validate that the namespace is at least one level deep
        if (false === strpos($namespace, '\\')) {
            $msg = array();
            $msg[] = sprintf('The namespace must contain a vendor namespace (e.g. "VendorName\%s" instead of simply "%s").', $namespace, $namespace);

            throw new \InvalidArgumentException(implode("\n\n", $msg));
        }

        return $namespace;
    }


    public static function validateBundleType($type)
    {
        $type = strtolower($type);

        if (!in_array($type, array('channel', 'location', 'activity', 'operation'))) {
            throw new \RuntimeException(sprintf('Type "%s" is not supported.', $type));
        }

        return $type;
    }
   

    public static function getReservedWords()
    {
        return array(
            'abstract',
            'and',
            'array',
            'as',
            'break',
            'callable',
            'case',
            'catch',
            'class',
            'clone',
            'const',
            'continue',
            'declare',
            'default',
            'do',
            'else',
            'elseif',
            'enddeclare',
            'endfor',
            'endforeach',
            'endif',
            'endswitch',
            'endwhile',
            'extends',
            'final',
            'finally',
            'for',
            'foreach',
            'function',
            'global',
            'goto',
            'if',
            'implements',
            'interface',
            'instanceof',
            'insteadof',
            'namespace',
            'new',
            'or',
            'private',
            'protected',
            'public',
            'static',
            'switch',
            'throw',
            'trait',
            'try',
            'use',
            'var',
            'while',
            'xor',
            'yield',
            '__CLASS__',
            '__DIR__',
            '__FILE__',
            '__LINE__',
            '__FUNCTION__',
            '__METHOD__',
            '__NAMESPACE__',
            '__TRAIT__',
            '__halt_compiler',
            'die',
            'echo',
            'empty',
            'exit',
            'eval',
            'include',
            'include_once',
            'isset',
            'list',
            'require',
            'require_once',
            'return',
            'print',
            'unset',
        );
    }
}
