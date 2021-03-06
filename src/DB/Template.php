<?php

declare(strict_types=1);

namespace EmailTemplates\DB;

use StORM\Entity;

/**
 * @table{"name":"email_template"}
 */
class Template extends Entity
{
	/**
	 * @column
	 */
	public string $name;
	
	/**
	 * @column{"type":"text"}
	 */
	public ?string $description;
	
	/**
	 * @column{"mutations":true}
	 */
	public ?string $subject;
	
	/**
	 * @column
	 */
	public ?string $email;
	
	/**
	 * @column
	 */
	public ?string $cc;

	/**
	 * @column
	 */
	public ?string $replyTo;
	
	/**
	 * @column
	 */
	public ?string $alias;
	
	/**
	 * @column
	 */
	public ?string $layout;
	
	/**
	 * @column{"type":"enum","length":"'incoming','outgoing'"}
	 */
	public string $type;
	
	/**
	 * @column
	 */
	public bool $active = true;
	
	/**
	 * @column{"type":"longtext","mutations":true}
	 */
	public ?string $text;
	
	/**
	 * @column{"type":"longtext","mutations":true}
	 */
	public ?string $html;
}
