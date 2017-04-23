<?php
namespace Evoweb\SfRegister\ViewHelpers;

/***************************************************************
 * Copyright notice
 *
 * (c) 2011-15 Sebastian Fischer <typo3@evoweb.de>
 * (c) 2011-15 Justin Kromlinger
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Viewhelper to output a captcha in a form
 * <code title="Usage">
 * {namespace register=Evoweb\SfRegister\ViewHelpers}
 * <register:languageKey type="languages"/>
 * {register:languageKey(type: 'countries')}
 * </code>
 */
class LanguageKeyViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper
{
    /**
     * Initialize arguments.
     *
     * @return void
     */
    public function initializeArguments()
    {
        $this->registerArgument('type', 'string', 'Purpose of this viewhelper. If it shoud check for certain static
            info tables or not');
    }

    /**
     * @return string
     */
    public function render()
    {
        $languageCode = '';
        if (TYPO3_MODE === 'FE') {
            if (isset($GLOBALS['TSFE']->config['config']['language'])) {
                $languageCode = $GLOBALS['TSFE']->config['config']['language'];
            }
        } elseif (strlen($GLOBALS['BE_USER']->uc['lang']) > 0) {
            $languageCode = $GLOBALS['BE_USER']->uc['lang'];
        }

        if ($languageCode && $this->hasArgument('type') && ($type = $this->getAllowedType())) {
            if ($type == 'countries') {
                $languageCode = $this->hasCountriesTableLanguageField($languageCode) ? $languageCode : '';
            } elseif ($type == 'languages') {
                $languageCode = $this->hasLanguagesTableLanguageField($languageCode) ? $languageCode : '';
            }
        }

        return $languageCode ?: 'en';
    }

    /**
     * @return string
     */
    protected function getAllowedType()
    {
        $type = $this->arguments['type'];

        return in_array($type, array('countries', 'languages')) ? $type : '';
    }

    /**
     * @param string $languageCode
     * @return bool
     */
    protected function hasCountriesTableLanguageField($languageCode)
    {
        $queryBuilder = $this->getQueryBuilder('static_countries');
        $columns = $queryBuilder->getConnection()->getSchemaManager()->listTableColumns('static_countries');

        $result = false;
        foreach ($columns as $column) {
            if ($column->getName() == 'cn_short_' . $languageCode) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * @param string $languageCode
     * @return bool
     */
    protected function hasLanguagesTableLanguageField($languageCode)
    {
        $queryBuilder = $this->getQueryBuilder('static_languages');
        $columns = $queryBuilder->getConnection()->getSchemaManager()->listTableColumns('static_languages');

        $result = false;
        foreach ($columns as $column) {
            if ($column->getName() == 'lg_name_' . $languageCode) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * @param string $tableName
     *
     * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
     */
    protected function getQueryBuilder($tableName)
    {
        return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Database\ConnectionPool::class
        )->getQueryBuilderForTable($tableName);
    }
}
