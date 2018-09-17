<?php

/**
* @package   s9e\zetabbcodes
* @copyright Copyright (c) 2018 The s9e Authors <https://github.com/s9e>
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\zetabbcodes;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use s9e\TextFormatter\Parser;
use s9e\TextFormatter\Parser\Tag;

class listener implements EventSubscriberInterface
{
	protected static $colPos = -1;

	public static function getSubscribedEvents()
	{
		return ['core.text_formatter_s9e_configure_after' => 'onConfigure'];
	}

	public function onConfigure($event)
	{
		$configurator = $event['configurator'];
		$configurator->tags['C']->filterChain->add(__CLASS__ . '::processCell')
			->resetParameters()
			->addParameterByName('tag')
			->addParameterByName('parser')
			->addParameterByName('openTags');
		$configurator->tags['TR']->filterChain->add(__CLASS__ . '::processRow')
			->resetParameters()
			->addParameterByName('tag')
			->addParameterByName('parser')
			->addParameterByName('openTags');

		// Interpret [quote=foo,(time=123)] as [quote author=foo time=123]
		$configurator->tags['quote']->attributePreprocessors->add(
			'author',
			'/^(?<author>.+),\\(time=(?<time>\\d+)\\)$/'
		);
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
}