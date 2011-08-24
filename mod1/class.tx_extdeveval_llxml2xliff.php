<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Xavier Perseguers <xavier@typo3.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Conversion of locallang.xlf files to XLIFF format.
 *
 * @author	Xavier Perseguers <xavier@typo3.org>
 * @package TYPO3
 * @subpackage tx_extdeveval
 * $Id$
 */
class tx_extdeveval_llxml2xliff {

	/** @var integer */
	protected $version;

	/** @var string */
	protected $extension;

	/**
	 * Default constructor.
	 */
	public function __construct() {
		$this->version = class_exists('t3lib_utility_VersionNumber')
				? t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version)
				: t3lib_div::int_from_ver(TYPO3_version);
	}

	/**
	 * Main function.
	 *
	 * @param string $xmlFile Absolute path to the selected ll-XML file
	 * @param string $extensionDir Extension dir (local, global, system) relative to PATH_site
	 * @return string HTML content
	 */
	public function main($xmlFile, $extensionDir) {
		$extension = substr($xmlFile, strlen(PATH_site . $extensionDir));
		$this->extension = substr($extension, 0, strpos($extension, '/'));

		if (@is_file($xmlFile))	{
			$fCheck = $this->checkXmlFilename($xmlFile);
			if (!$fCheck) {
				$languages = $this->getAvailableTranslations($xmlFile);
				$errors = array();
				foreach ($languages as $langKey) {
					$newFileName = $langKey === 'default' ? $xmlFile : $this->localizedFileRef($xmlFile, $langKey);
					$newFileName = preg_replace('#\.xml$#', '.xlf', $newFileName);
					if (@is_file($newFileName)) {
						$errors[] = 'ERROR: Output file "' . $newFileName . '" already exists!';
					}
				}
				if (count($errors) == 0) {
					if (!t3lib_div::_GP('doSave')) {
						$output = $this->renderSaveForm($xmlFile);
					} else {
						$output = '';
						foreach ($languages as $langKey) {
							$newFileName = $langKey === 'default' ? $xmlFile : $this->localizedFileRef($xmlFile, $langKey);
							$newFileName = preg_replace('#\.xml$#', '.xlf', $newFileName);

							$output .= $this->renderSaveDone($xmlFile, $newFileName, $langKey) . '<br />';
						}
					}
					return $output;
				}
			} else {
				return 'ERROR: ' . $fCheck;
			}
		}
	}

	/**
	 * Creates the form which allows to specify description, type etc and perform the conversion.
	 *
	 * @param string $xmlFile The PHP locallang.xml file reference (absolute)
	 * @return string HTML output (form)
	 */
	protected function renderSaveForm($xmlFile) {
		$languages = $this->getAvailableTranslations($xmlFile);

		$formOutput = '
		<form action="' . t3lib_div::linkThisScript() . '" method="post">
			XLIFF file(s) will be generated for following language(s):<br />
			' . implode(', ', $languages) . '<br /><br />
			<input type="submit" name="doSave" value="Convert" />
		</form>
		';

		return $formOutput;
	}

	/**
	 * Processing of the submitted form; Will create and write the XLIFF file and tell the new file name.
	 *
	 * @param string $xmlFile Absolute path to the locallang.xml file to convert
	 * @param string $newFilename The new file name to write to (absolute path, .xlf ending)
	 * @param string $langKey The language key
	 * @return string HTML text string message
	 */
	protected function renderSaveDone($xmlFile, $newFileName, $langKey) {

			// Initialize variables:
		$xml = array();
		$LOCAL_LANG = $this->getLLarray($xmlFile);

		$xml[] = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>';
		$xml[] = '<xliff version="1.0">';
		$xml[] = '	<file source-language="EN" target-language="' . ($langKey === 'default' ? 'en' : $langKey) . '"'
				. ' datatype="plaintext" original="messages" date="' . date('r') . '"'
				. ' product-name="' . $this->extension . '">';
		$xml[] = '		<header/>';
    	$xml[] = '		<body>';

		foreach ($LOCAL_LANG[$langKey] as $key => $data) {
			$xml[] = '			<trans-unit id="' . $key . '">';
			$xml[] = '				<source>' . $data[0]['source'] . '</source>';
			$xml[] = '				<target approved="yes">' . $data[0]['target'] . '</target>';
			$xml[] = '			</trans-unit>';
		}

		$xml[] = '		</body>';
		$xml[] = '	</file>';
		$xml[] = '</xliff>';

		if (!file_exists($newFileName)) {
			# debug(array($XML));
			t3lib_div::writeFile($newFileName, implode(LF, $xml));
			return 'File written to disk: ' . $newFileName;
		}
	}

	/**
	 * @param string $xmlFile Absolute reference to the ll-XML base locallang file
	 * @return array
	 */
	protected function getAvailableTranslations($xmlFile) {
		$ll = t3lib_div::xml2array(file_get_contents($xmlFile));
		if (!isset($ll['data'])) {
			throw new RuntimeException('data section not found in "' . $xmlFile . '"', 1314187884);
		}
		$availableTranslations = array_keys($ll['data']);

		if ($this->version < 4006000) {
			$languages = explode('|', TYPO3_languages);
		} else {
			/** @var $locales t3lib_l10n_Locales */
			$locales = t3lib_div::makeInstance('t3lib_l10n_Locales');
			$languages = $locales->getLocales();
		}

		foreach ($languages as $langKey) {
			if (!isset($availableTranslations[$langKey])) {
				// Localized addition?
				$lFileRef = $this->localizedFileRef($xmlFile, $langKey);
				if ($lFileRef && is_file($lFileRef)) {
					$availableTranslations[] = $langKey;
				}
			}
		}

		return $availableTranslations;
	}










	/****************************
	 *
	 * Helper functions
	 *
	 ****************************/

	/**
	 * Checking for a valid locallang*.xml filename.
	 *
	 * @param string $xmlFile Absolute reference to the ll-XML locallang file
	 * @return string Empty (false) return value means "OK" while otherwise is an error string
	 */
	protected function checkXmlFilename($xmlFile) {
		$basename = basename($xmlFile);
		if (!t3lib_div::isFirstPartOfStr($basename, 'locallang')) {
			return 'Filename didn\'t start with "locallang".';
		}

		return '';
	}

	/**
	 * Includes locallang files and returns raw $LOCAL_LANG array
	 *
	 * @param string $xmlFile Absolute reference to the ll-XML locallang file.
	 * @return array LOCAL_LANG array from ll-XML file (with all possible sub-files for languages included)
	 */
	function getLLarray($xmlFile) {
		$ll = t3lib_div::xml2array(file_get_contents($xmlFile));
		if (!isset($ll['data'])) {
			throw new RuntimeException('data section not found in "' . $xmlFile . '"', 1314187884);
		}
		$includedLanguages = array_keys($ll['data']);
		$LOCAL_LANG = array();

		if ($this->version < 4006000) {
			foreach ($includedLanguages as $langKey) {
				$llang = t3lib_div::readLLXMLfile($xmlFile, $langKey, $GLOBALS['LANG']->charSet);
				$LOCAL_LANG[$langKey] = $llang[$langKey];
			}

			$languages = explode('|', TYPO3_languages);
		} else {
			foreach ($includedLanguages as $langKey) {
				/** @var $parser t3lib_l10n_parser_Llxml */
				$parser = t3lib_div::makeInstance('t3lib_l10n_parser_Llxml');
				$llang = $parser->getParsedData($xmlFile, $langKey, $GLOBALS['LANG']->charSet);
				unset($parser);
				$LOCAL_LANG[$langKey] = $llang[$langKey];
			}

			/** @var $locales t3lib_l10n_Locales */
			$locales = t3lib_div::makeInstance('t3lib_l10n_Locales');
			$languages = $locales->getLocales();
		}

		foreach ($languages as $langKey) {
				// Localized addition?
			$lFileRef = $this->localizedFileRef($xmlFile, $langKey);
			if ($lFileRef && is_file($lFileRef)) {
				$llang = t3lib_div::readLLfile($lFileRef, $GLOBALS['LANG']->lang, $GLOBALS['LANG']->charSet);
				$LOCAL_LANG = t3lib_div::array_merge_recursive_overrule($LOCAL_LANG, $llang);
			}
		}

		return $LOCAL_LANG;
	}

	/**
	 * Returns localized fileRef ([langkey].locallang*.xml)
	 *
	 * @param string $fileRef Filename/path of a 'locallang*.xml' file
	 * @param string $lang Language key
	 * @return string Input filename with a '[lang-key].locallang*.xml' name if $this->lang is not 'default'
	 */
	protected function localizedFileRef($fileRef, $lang) {
		if ($lang !== 'default' && substr($fileRef, -4) === '.xml') {
			return dirname($fileRef) . '/' . $lang . '.' . basename($fileRef);
		}
	}
}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/extdeveval/mod1/class.tx_extdeveval_llxml2xlf.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/extdeveval/mod1/class.tx_extdeveval_llxml2xlf.php']);
}
?>