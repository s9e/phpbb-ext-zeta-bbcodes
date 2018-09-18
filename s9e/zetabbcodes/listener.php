<?php

/**
* @package   s9e\zetabbcodes
* @copyright Copyright (c) 2018 The s9e Authors <https://github.com/s9e>
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\zetabbcodes;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\auth\auth;
use phpbb\user;
use s9e\TextFormatter\Configurator\Items\Tag as TagConfig;
use s9e\TextFormatter\Parser;
use s9e\TextFormatter\Parser\Tag;

class listener implements EventSubscriberInterface
{
	protected $auth;
	protected static $colPos = -1;
	protected $user;

	public function __construct(auth $auth, user $user)
	{
		$this->auth = $auth;
		$this->user = $user;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.text_formatter_s9e_configure_after' => 'onConfigure',
			'core.text_formatter_s9e_renderer_setup'  => 'onRendererSetup'
		];
	}

	public function onConfigure($event)
	{
		$configurator = $event['configurator'];
		foreach ($configurator->tags as $tagName => $tag)
		{
			$methodName = 'onConfigure' . ucfirst(strtolower($tagName));
			if (method_exists($this, $methodName))
			{
				$this->$methodName($tag);
			}
		}

		// Make [html] an alias for [code=html]
		if (isset($configurator->tags['CODE']))
		{
			$configurator->BBCodes->add('HTML')->tagName = 'CODE';
		}
	}

	protected function onConfigureC(TagConfig $tag)
	{
		$tag->filterChain->append(__CLASS__ . '::processCell')
			->resetParameters()
			->addParameterByName('tag')
			->addParameterByName('parser')
			->addParameterByName('openTags');
	}

	protected function onConfigureCode(TagConfig $tag)
	{
		$tag->filterChain->prepend(__CLASS__ . '::processCode')
			->resetParameters()
			->addParameterByName('tag')
			->addParameterByName('text');
	}

	protected function onConfigureImg(TagConfig $tag)
	{
		// Add support for [img=width,height]
		$tag->attributePreprocessors->add('img', '/^(?<width>\\d+),(?<height>\\d+)$/');
		$this->addImgDimension($tag, 'height');
		$this->addImgDimension($tag, 'width');
	}

	protected function onConfigureQuote(TagConfig $tag)
	{
		// Interpret [quote=foo,(time=123)] as [quote author=foo time=123]
		$tag->attributePreprocessors->add(
			'author',
			'/^(?<author>.+),\\(time=(?<time>\\d+)\\)$/'
		);
	}

	protected function onConfigureTr(TagConfig $tag)
	{
		$tag->filterChain->append(__CLASS__ . '::processRow')
			->resetParameters()
			->addParameterByName('tag')
			->addParameterByName('parser')
			->addParameterByName('openTags');
	}

	public function onRendererSetup($event)
	{
		$event['renderer']->get_renderer()->setParameters([
			'USERNAME'   => $this->user->data['username'],
			'S_IS_STAFF' => $this->auth->acl_gets('a_', 'm_')
		]);
	}

	public static function processCell(Tag $tag, Parser $parser, array $openTags)
	{
		$i = count($openTags);
		if ($i > 0 && ($openTags[$i - 1]->getName() === 'TD' || $openTags[$i - 1]->getName() === 'TH'))
		{
			--$i;
			$parser->addEndTag($openTags[$i]->getName(), $tag->getPos(), 0);
		}
		if ($i < 2 || $openTags[$i - 1]->getName() !== 'TR' || $openTags[$i - 2]->getName() !== 'TABLE')
		{
			return true;
		}
		$tag->invalidate();

		$table = $openTags[$i - 2];
		$tr    = $openTags[$i - 1];

		// Prepare a TD or TH tag to replace this one
		$tagName = 'TD';
		if ($table->hasAttribute('headers'))
		{
			$tagName = 'TH';
			$table->setAttribute('headers_done', '');
		}

		// Determine the number of columns needed
		$cols = ($table->hasAttribute('cols')) ? $table->getAttribute('cols') : PHP_INT_MAX;
		if (!$tr->hasAttribute('cols'))
		{
			$tr->setAttribute('cols', $cols);
		}

		// Test whether any columns remain in this row
		$cols = $tr->getAttribute('cols');
		if ($cols < 1)
		{
			// Create a new row
			$parser->addStartTag('TR', $tag->getPos(), 0);
			$tagName = 'TD';
		}
		else
		{
			// Only decrement the counter if current tag does not replace a 0-width tag
			if ($tag->getPos() > self::$colPos)
			{
				--$cols;
			}
			self::$colPos = $tag->getPos();
			$tr->setAttribute('cols', $cols);
		}

		// Replace this tag
		$parser->addStartTag($tagName, $tag->getPos(), $tag->getLen());

		return false;
	}

	public static function processCode(Tag $tag, $text)
	{
		if (strtolower(substr($text, $tag->getPos(), $tag->getLen())) === '[html]')
		{
			$tag->setAttribute('lang', 'html');
		}

		return true;
	}

	public static function processRow(Tag $tag, Parser $parser, array $openTags)
	{
		self::$colPos = -1;
		$cnt = count($openTags);
		if ($cnt > 0 && $openTags[$cnt - 1]->getName() === 'TABLE')
		{
			$table = $openTags[$cnt - 1];
			if ($table->hasAttribute('headers_done'))
			{
				$table->removeAttribute('headers');
			}
		}
		if (!$tag->getLen())
		{
			$parser->addStartTag('C', $tag->getPos(), 0);
		}

		return true;
	}

	protected function addImgDimension(TagConfig $img, $attrName)
	{
		$attribute = $img->attributes->add($attrName);
		$attribute->filterChain->append('#uint');
		$attribute->required = false;
		if (strpos($img->template, '<xsl:copy-of select="@' . $attrName . '"/>') !== false)
		{
			return;
		}

		$dom = $img->template->asDOM();
		foreach ($dom->getElementsByTagName('img') as $node)
		{
			$node->appendChild($dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:copy-of'))
			     ->setAttribute('select', '@' . $attrName);
		}
		$dom->saveChanges();
	}
}