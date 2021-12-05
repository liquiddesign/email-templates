<?php

declare(strict_types=1);

namespace EmailTemplates\Bridges;

use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

class EmailTemplatesDI extends \Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'defaultFrom' => Expect::string()->required(),
			'defaultFromAlias' => Expect::string(),
			'layoutPath' => Expect::string(),
			'vars' => Expect::arrayOf('mixed', 'string'),
		]);
	}
	
	public function loadConfiguration(): void
	{
		/** @var \stdClass $config */
		$config = $this->getConfig();
		
		$builder = $this->getContainerBuilder();
		
		$builder->addDefinition($this->prefix('templates'), new ServiceDefinition())
			->setType(\EmailTemplates\DB\TemplateRepository::class)
			->setArguments([
				'defaultFrom' => $config->defaultFrom,
				'defaultFromAlias' => $config->defaultFromAlias,
				'layoutPath' => $config->layoutPath,
				'vars' => $config->vars,
			]);
	}
}
