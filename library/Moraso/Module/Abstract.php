<?php

/**
 * @author Christian Kehres <c.kehres@webtischlerei.de>
 * @copyright (c) 2013, webtischlerei <http://www.webtischlerei.de>
 */
abstract class Moraso_Module_Abstract extends Aitsu_Module_Abstract {

    protected $_renderOnMobile = true;
    protected $_renderOnTablet = true;

    public static function init($context, $instance = null) {

        $instance = is_null($instance) ? self :: _getInstance($context['className']) : $instance;

        if ($instance->_notForHumans()) {
            return;
        }

        $isMobile = Aitsu_Registry::get()->env->mobile->detect->isMobile;
        $isTablet = Aitsu_Registry::get()->env->mobile->detect->isTablet;
        
        if ($isMobile == 'is' && !$instance->_renderOnMobile) {
            return false;
        }

        if ($isTablet == 'is' && !$instance->_renderOnTablet) {
            return false;
        }

        if (($isMobile == 'is' && $isTablet == 'isNot') && (!$instance->_renderOnMobile && $instance->_renderOnTablet)) {
            return false;
        }

        if (!$instance->_isBlock) {
            Aitsu_Content_Edit :: isBlock(false);
        }

        $instance->_context = $context;

        $instance->_context['rawIndex'] = $instance->_context['index'];
        $instance->_context['index'] = preg_replace('/[^a-zA-Z_0-9]/', '_', $instance->_context['index']);
        $instance->_context['index'] = str_replace('.', '_', $instance->_context['index']);

        $instance->_index = empty($instance->_context['index']) ? 'noindex' : $instance->_context['index'];

        if (!empty($instance->_context['params'])) {
            $instance->_params = Aitsu_Util :: parseSimpleIni($instance->_context['params']);
        }

        if (!$instance->_allowEdit || (isset($instance->_params->edit) && !$instance->_params->edit)) {
            Aitsu_Content_Edit :: noEdit($instance->_moduleName, true);
        }

        $output_raw = $instance->_init();

        if ($instance->_cachingPeriod() > 0) {
            if ($instance->_get($context['className'], $output_raw)) {
                return $output_raw;
            }
        }

        $output_raw .= $instance->_main();

        $output = $instance->_transformOutput($output_raw);

        if ($instance->_cachingPeriod() > 0) {
            $instance->_save($output, $instance->_cachingPeriod());
        }

        if (Aitsu_Application_Status :: isEdit()) {
            $maxLength = 60;
            $index = strlen($context['index']) > $maxLength ? substr($context['index'], 0, $maxLength) . '...' : $context['index'];

            $match = array();
            if (trim($output) == '' && $instance->_allowEdit) {
                if (preg_match('/^Module_(.*?)_Class$/', $context['className'], $match)) {
                    $moduleName = str_replace('_', '.', $match[1]);
                } elseif (preg_match('/^Skin_Module_(.*?)_Class$/', $context['className'], $match)) {
                    $moduleName = str_replace('_', '.', $match[1]);
                } elseif (preg_match('/^Moraso_Module_(.*?)_Class$/', $context['className'], $match)) {
                    $moduleName = str_replace('_', '.', $match[1]);
                } else {
                    $moduleName = 'UNKNOWN';
                }
                if ($instance->_isBlock) {
                    return '' .
                            '<code class="aitsu_params" style="display:none;">' . $context['params'] . '</code>' .
                            '<div style="border:1px dashed #CCC; padding:2px 2px 2px 2px;">' .
                            '	<div style="height:15px; background-color: #CCC; color: white; font-size: 11px; padding:2px 5px 0 5px;">' .
                            '		<span style="font-weight:bold; float:left;">' . $index . '</span><span style="float:right;">Module <span style="font-weight:bold;">' . $moduleName . '</span></span>' .
                            '	</div>' .
                            '</div>';
                } else {
                    return '' .
                            '<span style="border:1px dashed #CCC; padding:2px 2px 2px 2px;">' .
                            '	' . $moduleName . ' :: ' . $index .
                            '</span>';
                }
            }

            if (!$instance->_isBlock) {
                return '' .
                        '<code class="aitsu_params" style="display:none;">' . $context['params'] . '</code>' .
                        '<span style="border:1px dashed #CCC; padding:2px 2px 2px 2px;">' . $output . '</span>';
            }

            if (isset($instance->_params->suppressWrapping) && $instance->_params->suppressWrapping) {
                return $output;
            }

            return '' .
                    '<code class="aitsu_params" style="display:none;">' . $context['params'] . '</code>' .
                    '<div>' . $output . '</div>';
        }

        return $output;
    }

    protected function _getView($view = null) {

        if ($this->_view != null) {
            return $this->_view;
        }

        $view = empty($view) ? new Zend_View() : $view;

        $module_parts = explode('_', get_class($this));

        $module_sliced = array_slice($module_parts, $module_parts[0] != 'Module' ? 2 : 1, -1);

        $modulePath = implode('/', $module_sliced);

        $heredity = Moraso_Util_Skin :: buildHeredity();

        $modulePaths = array();
        foreach ($heredity as $skin) {
            $modulePaths['skin'] = APPLICATION_PATH . "/skins/" . $skin . "/module/" . $modulePath . '/';
        }

        $modulePaths['moraso'] = realpath(APPLICATION_PATH . '/../library/') . '/Moraso/Module/' . $modulePath . '/';
        $modulePaths['aitsu'] = APPLICATION_PATH . '/modules/' . $modulePath . '/';

        foreach ($modulePaths as $path) {
            if (count(glob($path . '*.phtml')) > 0) {
                $view->setScriptPath($path);
                break;
            }
        }

        return $view;
    }

}