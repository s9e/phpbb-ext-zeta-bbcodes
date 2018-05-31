<?php

/**
* @package   s9e\zetabbcodes
* @copyright Copyright (c) 2018 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\zetabbcodes;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
			'core.text_formatter_s9e_configure_after'  => 'afterConfigure',
			'core.text_formatter_s9e_configure_before' => 'beforeConfigure'
		];
	}

	public function afterConfigure($event)
	{
		$configurator = $event['configurator'];

		// Interpret [quote=foo,123] as [quote author=foo time=123]
		$configurator->tags['quote']->attributePreprocessors->add(
			'author',
			'/^(?<author>.+),\\(time=(?<time>\\d+)\\)$/'
		);
	}

	public function beforeConfigure($event)
	{
		$configurator = $event['configurator'];

		$bbcodes = [
			[
				'[table={INT},{TEXT} rows={INT?} title={TEXT?} #createChild=TR]{TEXT2}[/table]',
				'<table>{TEXT2}</table>'
			],
			[
				'[tr #createChild=TD]{TEXT}[/tr]',
				'<tr>{TEXT}</tr>'
			],
			[
				'[td]{TEXT}[/td]',
				'<td>{TEXT}</td>'
			],
			[
				'[c $tagName=TD]{TEXT}[/c]',
				'<td>{TEXT}</td>'
			],
			[
				'[s]{TEXT}[/s]',
				'<del>{TEXT}</del>'
			],
			[
				'[sub]{TEXT}[/sub]',
				'<sub>{TEXT}</sub>'
			],
			[
				'[sup]{TEXT}[/sup]',
				'<sup>{TEXT}</sup>'
			],
			[
				'[big]{TEXT}[/big]',
				'<big>{TEXT}</big>'
			],
			[
				'[small]{TEXT}[/small]',
				'<small>{TEXT}</small>'
			],
			[
				'[bgcolor={COLOR}]{TEXT}[/bgcolor]',
				'<span style="background-color:{COLOR}">{TEXT}</span>'
			],
			[
				'[border={COLOR},{INT},{ALNUM} color={COLOR} width={INT} style={ALNUM}]{TEXT}[/border]',
				'<span style="border:{@style} {@width}px {@color}">{TEXT}</span>'
			],
			[
				'[center]{TEXT}[/center]',
				'<span style="display:block;text-align:center">{TEXT}</span>'
			],
			[
				'[right]{TEXT}[/right]',
				'<span style="display:block;text-align:right">{TEXT}</span>'
			],
			[
				'[font={FONTFAMILY}]{TEXT}[/font]',
				'<span style="font-family:{FONTFAMILY}">{TEXT}</span>'
			],
			[
				'[nocode $ignoreTags=true]{TEXT}[/nocode]',
				'{TEXT}'
			],
			[
				'[hr]',
				'<hr/>'
			],
			[
				'[spoiler]{TEXT}[/spoiler]',
				'<div class="spoiler_toggle" onclick="nextSibling.style.display=\'none\'.substr(nextSibling.style.display.length)">Spoiler: click to toggle</div><div class="spoiler" style="display:none">{TEXT}</div>'
			]
		];
		foreach ($bbcodes as list($usage, $template))
		{
			$configurator->BBCodes->addCustom($usage, $template);
		}
	}
}