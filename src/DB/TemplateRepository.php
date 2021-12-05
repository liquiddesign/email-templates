<?php

declare(strict_types=1);

namespace EmailTemplates\DB;

use Latte\Engine;
use Latte\Loaders\StringLoader;
use Latte\Policy;
use Latte\Sandbox\SecurityPolicy;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Bridges\ApplicationLatte\UIMacros;
use Nette\Mail\Message;
use StORM\DIConnection;
use StORM\Entity;
use StORM\Repository;
use StORM\SchemaManager;

/**
 * @extends \StORM\Repository<\EmailTemplates\DB\Template>
 */
class TemplateRepository extends Repository
{
	private LatteFactory $latteFactory;
	
	private Engine $latte;
	
	private string $defaultFrom;
	
	private ?string $defaultFromAlias;
	
	/**
	 * @var array<string, mixed>
	 */
	private array $vars;
	
	private ?string $layoutPath;
	
	/**
	 * @param string|null $layoutPath
	 * @param \Nette\Bridges\ApplicationLatte\LatteFactory $latteFactory
	 * @param string $defaultFrom
	 * @param array<string, mixed> $vars
	 * @param string|null $defaultFromAlias
	 * @param \StORM\DIConnection $connection
	 * @param \StORM\SchemaManager $schemaManager
	 */
	public function __construct(?string $layoutPath, LatteFactory $latteFactory, string $defaultFrom, array $vars, ?string $defaultFromAlias, DIConnection $connection, SchemaManager $schemaManager)
	{
		parent::__construct($connection, $schemaManager);
		
		$this->layoutPath = $layoutPath;
		$this->defaultFrom = $defaultFrom;
		$this->defaultFromAlias = $defaultFromAlias;
		$this->vars = $vars;
		$this->latteFactory = $latteFactory;
	}
	
	/**
	 * @param string $templateId
	 * @param array<int|string, string> $emails
	 * @param array<string, mixed> $vars
	 * @param string|null $mutation
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function createMessage(string $templateId, array $emails, array $vars = [], ?string $mutation = null): Message
	{
		$vars += $this->vars;
		
		/** @var \EmailTemplates\DB\Template $template */
		$template = $this->one($templateId, true, null, $mutation);
		
		$message = new Message();
		
		if ($template->type === 'outgoing') {
			$message->setFrom($template->email ?? $this->defaultFrom, $template->alias);
			
			foreach ($emails as $alias => $email) {
				$message->addTo($email, \is_string($alias) ? $alias : $email);
			}
		} else {
			$firstEmail = \reset($emails);
			$firstAlias = \array_key_first($emails);
			$message->setFrom($firstEmail !== false ? $firstEmail : $this->defaultFrom, \is_string($firstAlias) ? $firstAlias : $this->defaultFromAlias ?? $this->defaultFrom);
			$message->addTo($template->email ?? $this->defaultFrom, $template->alias);
		}
		
		if ($template->cc) {
			foreach (\explode(';', $template->cc ?? '') as $cc) {
				$message->addCc($cc);
			}
		}
		
		if ($template->replyTo) {
			$message->addReplyTo($template->replyTo);
		}
		
		$message->setSubject($this->renderToString($template->subject ?? '', $vars));
		
		$layoutContent = $this->getLayoutContent($template->layout);
		
		$message->setHtmlBody($this->renderToString($this->wrapLayoutLatte($template->html ?? $template->text ?? '', $layoutContent), $vars));
		
		$message->setBody($this->renderToString($this->wrapLayoutLatte($template->text ?? '', $layoutContent), $vars));
		
		return $message;
	}
	
	/**
	 * @param string $templateFilePath
	 * @param array<string, mixed> $vars
	 * @param string|null $layout
	 */
	public function parseEmailTemplate(string $templateFilePath, array $vars = [], ?string $layout = null): string
	{
		$vars += $this->vars;
		
		$layoutContent = $this->getLayoutContent($layout);
		
		if (!\is_file($templateFilePath)) {
			throw new \DomainException("Layout not found '$templateFilePath'");
		}
		
		$templateContent = \file_get_contents($templateFilePath);
		
		if ($templateContent === false) {
			throw new \DomainException("Layout cannot be loaded '$templateFilePath'");
		}
		
		return $this->renderToString($this->wrapLayoutLatte($templateContent, $layoutContent), $vars);
	}
	
	private function getLayoutContent(?string $layout): string
	{
		if ($layout) {
			if (!$this->layoutPath) {
				throw new \DomainException("Layout path is not defined");
			}
			
			$layoutFilePath = $this->layoutPath . '/' . $layout;
			
			if (!\is_file($layoutFilePath)) {
				throw new \DomainException("Layout not found '$this->layoutPath'");
			}
			
			$layoutContent = \file_get_contents($layoutFilePath);
			
			if ($layoutContent === false) {
				throw new \DomainException("Layout cannot be loaded");
			}
			
			return $layoutContent;
		}

		return '{include email_co}';
	}
	
	private function wrapLayoutLatte(string $string, string $layout): string
	{
		return "{define email_co}$string{/define}$layout";
	}
	
	/**
	 * @param string $str
	 * @param array<string, mixed> $vars
	 */
	private function renderToString(string $str, array $vars): string
	{
		return $this->getLatteEngine()->renderToString($str, $vars);
	}
	
	private function getLatteSecurityPolicy(): Policy
	{
		$policy = SecurityPolicy::createSafePolicy();
		$policy->allowMacros(['include']);
		$policy->allowProperties(\ArrayObject::class, (array)$policy::ALL);
		$policy->allowProperties(Entity::class, (array)$policy::ALL);
		$policy->allowMethods(Entity::class, (array)$policy::ALL);
		$policy->allowFilters(['price', 'date']);
		
		return $policy;
	}
	
	private function getLatteEngine(): Engine
	{
		if (isset($this->latte)) {
			return $this->latte;
		}
		
		$latte = $this->latteFactory->create();
		UIMacros::install($latte->getCompiler());
		$latte->setLoader(new StringLoader());
		$latte->setPolicy($this->getLatteSecurityPolicy());
		$latte->setSandboxMode();
		
		return $this->latte = $latte;
	}
}
