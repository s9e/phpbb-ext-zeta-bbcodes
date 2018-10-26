<?php

/**
* @package   s9e\zetabbcodes
* @copyright Copyright (c) 2018 The s9e Authors <https://github.com/s9e>
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\zetabbcodes\migrations;

class bbcodes extends \phpbb\db\migration\migration
{
	protected $bbcodes = [
		[
			'[table={PARSE=/^(?<cols>\\d+)\\s*,\\s*(?<title>.+)\\s*,\\s*(?<headers>\\d+)$/} table={PARSE=/^(?<cols>\\d+)\\s*,\\s*(?<title>.+)/} table={PARSE=/^(?<cols>\\d+)$/} cols={UINT?} headers={UINT?} title={TEXT?} #createChild=TR]{TEXT2}[/table]',
			'<table>
				<xsl:if test="@title">
					<thead>
						<tr>
							<th>
								<xsl:if test="@cols">
									<xsl:attribute name="colspan">
										<xsl:value-of select="@cols"/>
									</xsl:attribute>
								</xsl:if>
								<xsl:value-of select="@title"/>
							</th>
						</tr>
					</thead>
				</xsl:if>
				<tbody>
					<xsl:apply-templates/>
				</tbody>
			</table>'
		],
		[
			'[tr]{TEXT}[/tr]',
			'<tr>{TEXT}</tr>'
		],
		[
			'[td]{TEXT}[/td]',
			'<td>{TEXT}</td>'
		],
		[
			'[th]{TEXT}[/th]',
			'<th>{TEXT}</th>'
		],
		[
			'[c #closeParent=TD,TH]{TEXT}[/c]',
			''
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
			'[me]',
			'<xsl:value-of select="$USERNAME"/>'
		],
		[
			'[member]{TEXT}[/member]',
			'<xsl:choose>
				<xsl:when test="$S_USER_LOGGED_IN">{TEXT}</xsl:if>
				<xsl:otherwise>[Hidden Content: Login/Register to View]</xsl:otherwise>
			</xsl:choose>'
		],
		[
			'[staff]{TEXT}[/staff]',
			'<xsl:if test="$S_IS_STAFF">{TEXT}</xsl:if>'
		],
		[
			'[border={PARSE=/^(?<color>#?\\w+)(?:,(?<width>\\d+)(?:,(?<style>\\w+))?)?/} color={COLOR} width={UINT;defaultValue=1} style={IDENTIFIER;defaultValue=solid}]{TEXT}[/border]',
			'<span style="border:{@width}px {@style} {@color}">{TEXT}</span>'
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

	public function update_data()
	{
		return [['custom', [[$this, 'insertBBCodes']]]];
	}

	public function insertBBCodes()
	{
		$sql      = 'SELECT MAX(bbcode_id) AS bbcode_id FROM ' . BBCODES_TABLE;
		$result   = $this->sql_query($sql);
		$bbcodeId = $this->db->sql_fetchfield('bbcode_id') ?: NUM_CORE_BBCODES + 1;
		$this->db->sql_freeresult($result);

		$bbcodes = $this->bbcodes;
		$sql     = 'SELECT bbcode_tag FROM ' . BBCODES_TABLE;
		$result  = $this->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$bbcodeName = rtrim(strtolower($row['bbcode_tag']), '=');
			unset($bbcodes[$bbcodeName]);
		}
		$this->db->sql_freeresult($result);

		foreach ($bbcodes as $pair)
		{
			$row = [
				'bbcode_id'           => ++$bbcodeId,
				'bbcode_tag'          => preg_replace('(^\\[(\\w+).*)s', '$1', $pair[0]),
				'bbcode_helpline'     => '',
				'display_on_posting'  => 0,
				'bbcode_match'        => $pair[0],
				'bbcode_tpl'          => $pair[1],
				'first_pass_match'    => '((?!))',
				'first_pass_replace'  => '',
				'second_pass_match'   => '((?!))',
				'second_pass_replace' => ''
			];

			$sql = 'INSERT INTO ' . BBCODES_TABLE . ' ' . $this->db->sql_build_array('INSERT', $row);
			$this->sql_query($sql);
		}
	}
}