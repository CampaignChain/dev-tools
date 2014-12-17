# CampaignChain Module Generator

The CampaignChain module generator is an interactive tool to help generate new CampaignChain modules for development. It also generates the necessary packaging for modules in Composer-friendly format.

## Installation

To install the CampaignChain module generator, follow these steps:

1. Update your project's composer.json file to include the "campaignchain/dev-tools" package.

2. Run "composer update" in your project directory.

3. Add the following line to the end of the registerBundles::bundles array in your app/AppKernel.php file:
```
  new CampaignChain\GeneratorBundle\CampaignChainGeneratorBundle(),
```

4. If necessary, manually update your project's Composer autoload_psr4.php file to load the necessary classes:
```
  return array(
    // ....
    'CampaignChain\\GeneratorBundle\\' => array($vendorDir . '/campaignchain/dev-tools'),
  );
```

## Usage
  
To use the CampaignChain module generator, first ensure that you're prepared with the basic information for your module, as listed below: 

- The module name
- The module identifier
- The module description
- The module vendor's name and email address
- The module license
- The bundle namespace
- The bundle name
- Any module-specific parameters

Refer to [the CampaignChain developer documentation](http://doc.campaignchain.com/current/developer/book/modules.html) to learn more.

The CampaignChain module generator will provide hints and tips throughout the module generation process, so don't worry if you don't have it all ready when you begin. 

You can now start the module generator with the following command:

```
  php app/console campaignchain:generate:module
```  