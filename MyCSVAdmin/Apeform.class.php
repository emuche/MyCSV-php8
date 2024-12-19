<?php

class Apeform
{
    
    var $_rows = array();
    var $_encType = "";
    var $_attributes = array();
    var $magicQuotes = null;
    var $_isSubmitted = false;
    var $_hasErrors = false;
    var $id = "form";
    var $anchor = null;
    var $autoAccesskeys = false;
    var $maxLength = 255;
    var $size = 40;
    var $tmpDir = "/tmp";
    var $templates = array(
        'form' =>
            "<p><table border=\"0\" summary=\"\">\n{input}</table></p>",
        'header' =>
            "<tr{class}>\n<th colspan=\"2\">{header}</th>\n</tr>\n",
        'input' =>
            "<tr{class}>\n<td align=\"right\" valign=\"top\">{label}</td>\n<td valign=\"top\">{input}{help}</td>\n</tr>\n",
        'label' =>
            "{label}:",
        'error' =>
            '<strong class="error">{error}:</strong>',
        'help' =>
            '<br /><small>{help}</small>',
        'accesskey' =>
            '<span style="text-decoration:underline">{accesskey}</span>'
    );

    
    function Apeform($maxLength = 0, $size = 0, $id = null, $magicQuotes = null)
    {
        if ($maxLength > 0) $this->maxLength = (int)$maxLength;
        if ($size > 0) $this->size = (int)$size;
        if (is_bool($id)) { $magicQuotes = $id; unset($id); }
        elseif (isset($id) && strlen($id)) $this->id = $id;

        if (isset($magicQuotes)) $this->magicQuotes = $magicQuotes;
        if (!isset($this->magicQuotes)) $this->magicQuotes = get_magic_quotes_gpc();
    }

    function &header($header)
    {
        $this->_rows[count($this->_rows)] = array('type' => "header",
            'header' => $header,
            'name' => false);
        return $header;
    }

    function &staticText($label = "", $help = "", $defaultValue = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "static",
            'label' => $label,
            'help' => $help,
            'value' => $defaultValue,
            'name' => $name);
        return $this->_fetchPostedValue();
    }

    function &text($label = "", $help = "", $defaultValue = "", $maxLength = 0,
        $size = 0)
    {
        $count = max(count($defaultValue), count($maxLength), count($size));
        if (is_string($help)) $help = explode("\t", $help, 1 + $count);
        if ($count > 1)
        {
            $defaultValue = (array)$defaultValue;
            for ($i = 1; $i < $count; ++$i)
            {
                if (!isset($help[$i])) $help[$i] = "\n";
                if (!isset($defaultValue[$i])) $defaultValue[$i] = "";
            }
        }
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "text",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'value' => $defaultValue,
            'maxLength' => $maxLength,
            'size' => $size,
            'name' => $name);
        $value = &$this->_fetchPostedValue();
        if (is_array($value) && count($value) < 2) return $value[0];
        else return $value;
    }

    function &password($label = "", $help = "", $defaultValue = "",
        $maxLength = 0, $size = 0)
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "password",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'value' => $defaultValue,
            'maxLength' => $maxLength,
            'size' => $size,
            'name' => $name);
        return $this->_fetchPostedValue();
    }

    function &textarea($label = "", $help = "", $defaultValue = "", $rows = 0,
        $cols = 0, $wrap = "virtual")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "textarea",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'value' => $defaultValue,
            'rows' => $rows,
            'cols' => $cols,
            'wrap' => empty($wrap) ? "off" : $wrap,
            'name' => $name);
        return $this->_fetchPostedValue();
    }

    function &hidden($defaultValue = "", $name = "")
    {
        $id = count($this->_rows);
        $name = $name ? $name : ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "hidden",
          'value' => $defaultValue,
          'name' => $name);
        return $this->_fetchPostedValue();
    }

    function &checkbox($label, $help = "", $options = "", $defaultValue = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        if (!$options) $options = array($label => "");
        $this->_rows[$id] = array('type' => "checkbox",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'options' => $this->_explodeOptions($options),
            'name' => $name);
        if (count($this->_rows[$id]['options']) > 1)
        {
            $this->_rows[$id]['value'] = $this->_isSubmitted ? array() :
                $this->_explodeOptions($defaultValue);
        }
        else
        {
            $this->_rows[$id]['value'] = $this->_isSubmitted ? "" :
                $defaultValue;
        }
        return $this->_fetchPostedValue();
    }

    function &radio($label, $help, $options, $defaultValue = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "radio",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'options' => $this->_explodeOptions($options),
            'value' => $defaultValue,
            'name' => $name);
        if (!isset($this->_rows[$id]['options'][$this->magicQuotes ?
            addslashes($defaultValue) : $defaultValue]))
        {
            $this->_rows[$id]['value'] =
                array_search($defaultValue, $this->_rows[$id]['options']);
            if ($this->magicQuotes)
            {
                $this->_rows[$id]['value'] = stripslashes($this->_rows[$id]['value']);
            }
        }
        return $this->_fetchPostedValue();
    }

    function &select($label, $help, $options, $defaultValue = "", $size = 1)
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "select",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'options' => $this->_explodeOptions($options),
            'value' => $defaultValue,
            'size' => $size,
            'name' => $name);
        if (!isset($this->_rows[$id]['options'][$this->magicQuotes ?
            addslashes($defaultValue) : $defaultValue]))
        {
            $this->_rows[$id]['value'] =
                array_search($defaultValue, $this->_rows[$id]['options']);
            if ($this->magicQuotes)
            {
                $this->_rows[$id]['value'] = stripslashes($this->_rows[$id]['value']);
            }
        }
        return $this->_fetchPostedValue();
    }

    function &file($label = "", $help = "", $size = 0)
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "file",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'value' => false,
            'size' => $size,
            'name' => $name);
        $this->_encType = "multipart/form-data";

        if (isset($GLOBALS['HTTP_POST_FILES'][$name]) && !isset($_FILES[$name]))
            $_FILES[$name] = &$GLOBALS['HTTP_POST_FILES'][$name];
        if (isset($_FILES[$name]) && $_FILES[$name]['size'])
        {
            $this->_rows[$id]['value'] = $_FILES[$name];
            $postedName = &$this->_rows[$id]['value']['name'];
            if (get_magic_quotes_gpc()) $postedName = stripslashes($postedName);
            if ($this->magicQuotes) $postedName = addslashes($postedName);
        }
        elseif (isset($_POST[$name . "h"]))
        {
            $this->_rows[$id]['value'] =
                unserialize(stripslashes($_POST[$name . "h"]));
        }
        elseif (isset($GLOBALS['HTTP_POST_VARS'][$name . "h"]))
        {
            $this->_rows[$id]['value'] =
                unserialize(stripslashes($GLOBALS['HTTP_POST_VARS'][$name . "h"]));
        }
        if ($this->_rows[$id]['value'] || isset($_FILES[$name]))
            $this->_isSubmitted = true;

        if ($this->_rows[$id]['value'])
        {
            $this->_rows[$id]['value']['unixname'] =
                $this->_getUnixName($this->_rows[$id]['value']['name']);
            $this->_rows[$id]['value']['type'] =
                preg_replace('{^image\W\wjpe?g$}is', 'image/jpeg',
                $this->_rows[$id]['value']['type']);

            if (is_uploaded_file($this->_rows[$id]['value']['tmp_name']))
            {
                $tempnam = tempnam($realpath = realpath($this->tmpDir), "tmp");
                if (!$this->_doGarbageCollection($tempnam))
                {
                    user_error("Apeform::file() failed, tmpDir is not set properly",
                        E_USER_WARNING);
                    return $this->_rows[$id]['value'] = false;
                }
                $extension = strrchr($this->_rows[$id]['value']['name'], '.');
                rename($tempnam, $tempnam . $extension);
                $tempnam .= $extension;
                if (!move_uploaded_file($this->_rows[$id]['value']['tmp_name'],
                    $tempnam))
                {
                    return $this->_rows[$id]['value'] = false;
                }
                if (dirname($tempnam) == $realpath)
                {
                    $tempnam = $this->tmpDir . "/" . basename($tempnam);
                }
                $this->_rows[$id]['value']['tmp_name'] = $tempnam;
            }
            if (!is_file($this->_rows[$id]['value']['tmp_name']))
                $this->error();
        }
        return $this->_rows[$id]['value'];
    }

    function _getUnixName($name, $maxLength = 64)
    {
        $name = str_replace("�", "ss", $name);
        $name = preg_replace('/[\x8C\x9C����������]/', '\0e', $name);
        $name = strtr($name,
            "\x80\x83\x8A\x8C\x8E\x96\x97\x9A\x9C\x9E\x9F������������" .
            "��������������������������������������������������������������",
            "EfSOZ--sozYcLYca-r23u1o" .
            "AAAAAAACEEEEIIIIDNOOOOOxOUUUUyTaaaaaaaceeeeiiiidnoooooouuuuyty");
        $name = preg_replace('/[^a-z0-9.-]+/i', '_', $name);
        $name = preg_replace('/_*\b_*/', '', $name);
        while (strlen($name) > $maxLength)
            $name = preg_replace('/.\b/', '', $name, 1);
        return $name;
    }

   
    function _doGarbageCollection($filename, $timeout = 1440)
    {
        $dir = dirname($filename) . "/";
        if (!($fp = opendir($dir))) return false;
        while (($file = readdir($fp)) !== false)
        {
            if (strpos($file, "tmp") === 0 && filemtime($dir . $file) < time() - $timeout)
            {
                @unlink($dir . $file);
            }
        }
        closedir($fp);
        return true;
    }

    function submit($value = "", $help = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => 'submit',
            'value' => empty($value) ? array("") : $this->_explodeOptions($value),
            'help' => $help,
            'name' => $name);
        if (isset($_POST[$name]))
        {
            $postedValue = $_POST[$name];
        }
        elseif (isset($GLOBALS['HTTP_POST_VARS'][$name]))
        {
            $postedValue = $GLOBALS['HTTP_POST_VARS'][$name];
        }
        if (isset($postedValue))
        {
            if (get_magic_quotes_gpc()) $postedValue = stripslashes($postedValue);
            if (empty($value)) $postedValue = true;
            elseif (!in_array($postedValue, $this->_rows[$id]['value'])) return false;
            elseif ($this->magicQuotes) $postedValue = addslashes($postedValue);
            $this->_isSubmitted = true;
            return $postedValue;
        }
        return false;
    }

    function image($src, $help = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "image",
            'value' => $this->_explodeOptions($src),
            'help' => $help,
            'name' => $name);
        if (isset($_POST[$name . '_x']))
        {
            $x = $_POST[$name . '_x'];
            $y = $_POST[$name . '_y'];
        }
        elseif (isset($GLOBALS['HTTP_POST_VARS'][$name. '_x']))
        {
            $x = $GLOBALS['HTTP_POST_VARS'][$name . '_x'];
            $y = $GLOBALS['HTTP_POST_VARS'][$name . '_y'];
        }
        if (isset($x))
        {
            $this->_isSubmitted = true;
            return array(0 => $x, 1 => $y, 'x' => $x, 'y' => $y);
        }
        return false;
    }

    function _addAcceskey($label)
    {
        if (!$this->autoAccesskeys || stristr($label, "<u>")) return $label;
        $c = strtolower(preg_replace('/[^a-z]+/isS', '', $label));
        for ($i = 0, $len = strlen($c); $i < $len; ++$i)
        {
            if (empty($this->_accesskeys[$c{$i}]))
            {
                $this->_accesskeys[$c{$i}] = true;
                return preg_replace('/' . $c{$i} . '/i', '<u>\0</u>', $label, 1);
            }
        }
        return $label;
    }

   
    function _explodeOptions($options)
    {
        if (!is_array($options))
        {
            if (strlen($options) < 1) return array();
            if (strpos($options, "\t") === false)
            {
                $options = strtr($options, array("\\\\" => "\\", "\|" => "|", "|" => "\t"));
            }
            $exploded = explode("\t", $options);
            $options = array();
            foreach ($exploded as $value) $options[$value] = $value;
        }
        if ($this->magicQuotes)
        {
            $array = $options;
            $options = array();
            foreach ($array as $key => $value) $options[addslashes($key)] = $value;
        }
        return $options;
    }

    function &_fetchPostedValue()
    {
        $element = &$this->_rows[count($this->_rows) - 1];
        if (isset($_POST[$element['name']]))
        {
            $postedValue = $_POST[$element['name']];
        }
        elseif (isset($GLOBALS['HTTP_POST_VARS'][$element['name']]))
        {
            $postedValue = $GLOBALS['HTTP_POST_VARS'][$element['name']];
        }
        if (isset($postedValue))
        {
            if (get_magic_quotes_gpc())
            {
                $postedValue = is_array($postedValue)
                    ? array_map('stripslashes', $postedValue)
                    : stripslashes($postedValue);
            }
            if (strpos(implode("", (array)$postedValue), "\0"))
                $this->error();
            $postedValue = str_replace("\0", "", $postedValue);
            switch ($element['type'])
            {
                case "text":
                case "password":
                    if (strpos(implode("", (array)$postedValue), "\n"))
                        $this->error();
                    $postedValue = preg_replace('/[\r\n]+/s', ' ', $postedValue);
                    foreach ((array)$postedValue as $i => $v)
                    {
                        if (!is_array($element['maxLength']) && !empty($element['maxLength']))
                            $l = $element['maxLength'];
                        elseif (!empty($element['maxLength'][$i]))
                            $l = $element['maxLength'][$i];
                        else $l = $this->maxLength;
                        if (strlen($v) > $l)
                        {
                            $this->error();
                            if (is_array($postedValue)) $postedValue[$i] = substr($v, 0, $l);
                            else $postedValue = substr($v, 0, $l);
                        }
                    }
                    break;
                case "checkbox":
                case "radio":
                case "select":
                    foreach ((array)$postedValue as $i => $v)
                    {
                        if (!isset($element['options'][$v]))
                        {
                            $this->error();
                            if (is_array($postedValue)) unset($postedValue[$i]);
                            else $postedValue = "";
                        }
                    }
                    break;
            }
            $element['value'] = $postedValue;
            $this->_isSubmitted = true;
        }
        if ($this->magicQuotes)
        {
            $element['value'] = is_array($element['value'])
                ? array_map('addslashes', $element['value'])
                : addslashes($element['value']);
        }
        return $element['value'];
    }

    function getName($offset = -1)
    {
        $id = ($offset < 0) ? (count($this->_rows) + $offset) : $offset;
        return isset($this->_rows[$id]) ? $this->_rows[$id]['name'] : false;
    }

    function addAttribute($attribute, $value = null)
    {
        if (!isset($value)) $value = $attribute;
        if (empty($this->_rows) || strcasecmp($attribute, "onsubmit") == 0)
        {
            $a = &$this->_attributes[$attribute];
        }
        else
        {
            $a = &$this->_rows[count($this->_rows) - 1]['attributes'][$attribute];
        }
        if (empty($a)) $a = ""; else $a .= " ";
        return $a .= $value;
    }

    function addClass($class)
    {
        if (empty($this->_rows)) return false;
        $a = &$this->_rows[count($this->_rows) - 1]['class'];
        if (empty($a)) $a = ""; else $a .= " ";
        return $a .= $class;
    }

    function error($message = "", $offset = -1)
    {
        $id = ($offset < 0) ? (count($this->_rows) + $offset) : $offset;
        if (!isset($this->_rows[$id])) return false;
        if ($this->_isSubmitted && empty($this->_rows[$id]['error']))
        {
            $this->_rows[$id]['error'] = $message;
            if ($this->_rows[$id]['type'] == "password")
            {
                $this->_rows[$id]['value'] = "";
            }
            elseif ($this->_rows[$id]['type'] == "file")
            {
                @unlink($this->_rows[$id]['value']['tmp_name']);
                $this->_rows[$id]['value'] = false;
            }
            $this->_hasErrors = true;
        }
    }

    function isValid()
    {
        return $this->_isSubmitted && !$this->_hasErrors;
    }

    function toHTML($setFocus = null)
    {
        $form = '<form action="';
        $form .= isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] :
            $GLOBALS['HTTP_SERVER_VARS']['PHP_SELF'];
        if (!empty($_SERVER['QUERY_STRING']))
        {
            $form .= "?" . htmlspecialchars($_SERVER['QUERY_STRING']);
        }
        elseif (!empty($GLOBALS['HTTP_SERVER_VARS']['QUERY_STRING']))
        {
            $form .= "?" . htmlspecialchars($GLOBALS['HTTP_SERVER_VARS']['QUERY_STRING']);
        }
        if (!isset($this->anchor)) $form .= "#" . $this->id;
        elseif ($this->anchor)      $form .= "#" . $this->anchor;
        $form .= '"';
        if (!empty($this->_encType)) $form .= ' enctype="' . $this->_encType . '"';
        $form .= $this->_implodeAttributes($this->_attributes);
        $form .= ' id="' . $this->id . '" method="post">';
        $elements = "";

        reset($this->_rows);
        while (list($id, $row) = each($this->_rows))
        {
            $nameAttr = ' name="' . $row['name'] . '"';
            $idAttr = ' id="' . $row['name'] . 'i"';
            $genericAttr = $this->_implodeAttributes($row['attributes']);
            if (isset($row['help']) && is_string($row['help']))
            {
                $row['help'] = explode("\t", $row['help'], 2);
            }
            if (!empty($row['help'][0]))
            {
                $genericAttr .= ' title="' . str_replace('"', '&quot;',
                    strip_tags($row['help'][0])) . '"';
            }
            $input = '';
            switch ($row['type'])
            {
                case "header":
                    $elements .= str_replace(array("{header}", "{class}"),
                        array($row['header'], empty($row['class']) ? "" : ' class="' . $row['class'] . '"'),
                        $this->getTemplate('header', $row['name']));
                    continue 2;
                case "hidden":
                    $form .= '<input type="hidden"' . $nameAttr . ' value="' .
                        htmlspecialchars($this->magicQuotes ?
                        stripslashes($row['value']) : $row['value']) . '" />';
                    continue 2;
                case "static":
                    $input = nl2br(htmlspecialchars($row['value'])) .
                        '<input type="hidden"' . $nameAttr . ' value="' .
                        htmlspecialchars($this->magicQuotes ?
                        stripslashes($row['value']) : $row['value']) . '" />';
                    break;
                case "textarea":
                    $input = '<textarea' . $nameAttr;
                    $input .= ' cols="' . (empty($row['cols']) ? $this->size : $row['cols']) . '"';
                    $input .= ' rows="' . (empty($row['rows']) ? 3 : $row['rows']) . '"';
                    $input .= ' wrap="' . (empty($row['wrap']) ? "virtual" : $row['wrap']) . '"';
                    $input .= $idAttr . $genericAttr . '>';
                    $input .= htmlspecialchars($this->magicQuotes ?
                        stripslashes($row['value']) : $row['value']);
                    $input .= '</textarea>';
                    break;
                case "select":
                    $input = '<select' . $nameAttr . ' size="' . $row['size'] . '"';
                    $input .= $idAttr . $genericAttr . '>';
                    while (list($key, $value) = each($row['options']))
                    {
                        $input .= '<option value="';
                        $input .= htmlspecialchars($this->magicQuotes ?
                            stripslashes($key) : $key);
                        $input .= '"';
                        if (strcmp($key, $row['value']) == 0) $input .= ' selected="selected"';
                        $input .= '>' . str_replace("<", "&lt;", $value) . "</option>\n";
                    }
                    $input .= '</select>';
                    break;
                case "radio":
                case "checkbox":
                    $i = 0;
                    while (list($key, $value) = each($row['options']))
                    {
                        $input .= '<input type="' . $row['type'] . '" name="' . $row['name'];
                        if ($row['type'] == 'checkbox' && count($row['options']) > 1)
                        {
                            $input .= '[]';
                        }
                        $input .= '" value="' . htmlspecialchars(
                            $this->magicQuotes ? stripslashes($key) : $key);
                        $input .= '"';
                        if (in_array($key, (array)$row['value']))
                        {
                            $input .= ' checked="checked"';
                        }
                        $input .= ' id="' . $row['name'] . 'i' . $i . '"';
                        $input .= $genericAttr . ' />';
                        if ($value)
                        {
                            $input .= '<label for="' . $row['name'] . 'i' . $i . '"';
                            if (preg_match('/<u>(\w)/i', $value, $match))
                            {
                                $input .= ' accesskey="' . strtolower($match[1]) . '"';
                            }
                            $input .= ">" . $value . "</label>\n";
                        }
                        $i++;
                    }
                    break;
                case "submit":
                    while (list($i, $value) = each($row['value']))
                    {
                        $input .= '<input type="submit"' . $nameAttr;
                        if (!empty($value))
                        {
                            $input .= ' value="' . strtr($this->magicQuotes ?
                                stripslashes($value) : $value, array('"' =>
                                '&quot;', '<' => '&lt;', '>' => '&gt;')) . '"';
                        }
                        if (count($row['value']) == 1) $input .= ' accesskey="s"';
                        $input .= $genericAttr . " />\n";
                    }
                    break;
                case "image":
                    while (list($i, $value) = each($row['value']))
                    {
                        $input .= '<input type="image"' . $nameAttr . ' src="' .
                            htmlspecialchars($this->magicQuotes ?
                            stripslashes($value) : $value) . '"' . $genericAttr
                            . " />\n";
                    }
                    break;
                case 'text':
                    $c = count($row['value']);
                    $maxes = (array)$row['maxLength'];
                    $sizes = (array)$row['size'];
                    foreach ((array)$row['value'] as $i => $value)
                    {
                        if (!isset($maxes[$i])) $maxes[$i] = $maxes[$i - 1];
                        if (!isset($sizes[$i])) $sizes[$i] = $sizes[$i - 1];
                        if ($input && isset($row['help'][1]))
                        {
                            $input .= current(array_splice($row['help'], 1, 1));
                        }
                        $input .= '<input type="' . $row['type'] . '" name="' . $row['name'];
                        if ($c > 1) $input .= '[]';
                        $input .= '" value="';
                        $input .= htmlspecialchars($this->magicQuotes ?
                            stripslashes($value) : $value);
                        $input .= '" maxlength="' . ($maxes[$i] ? $maxes[$i] :
                            $this->maxLength) . '"';
                        $size = (int)round($this->size / $c);
                        if (!empty($maxes[$i]) && $maxes[$i] < $size)
                        {
                            $size = $maxes[$i];
                        }
                        if ($sizes[$i]) $size = $sizes[$i];
                        $input .= ' size="' . $size . '"';
                        $input .= ' id="' . $row['name'] . 'i' . ($c > 1 ? $i : '') . '"';
                        $input .= $genericAttr . ' />';
                    }
                    break;
                default:
                    $input = '<input type="' . $row['type'] . '"' . $nameAttr;
                    if (isset($row['value']) && $row['type'] != "file")
                    {
                        $input .= ' value="';
                        $input .= htmlspecialchars($this->magicQuotes ?
                            stripslashes($row['value']) : $row['value']);
                        $input .= '"';
                    }
                    if (isset($row['maxLength']))
                    {
                        $input .= ' maxlength="' . ($row['maxLength'] ?
                            $row['maxLength'] : $this->maxLength) . '"';
                    }
                    if (isset($row['size']))
                    {
                        $size = $this->size - ($row['type'] == "file" ? 22 : 0);
                        if (!empty($row['maxLength']) && $row['maxLength'] < $size)
                        {
                            $size = $row['maxLength'];
                        }
                        if ($row['size']) $size = $row['size'];
                        if ($row['type'] == "file" && !empty($row['value'])) $size = 5;
                        $input .= ' size="' . $size . '"';
                    }
                    $input .= $idAttr . $genericAttr . ' />';
                    if ($row['type'] == "file" && !empty($row['value']))
                    {
                        $name = $row['value']['name'];
                        $length = max($this->size - 32, 12);
                        if (strlen($name) > $length)
                        {
                            $name = substr($name, 0, $length) . '...';
                        }
                        $input = $name . ' ' . $input;
                        $input .= '<input type="hidden" name="' . $row['name'] . 'h"';
                        $input .= ' value="' .
                            htmlspecialchars(serialize($row['value'])) . '" />';
                    }
            }

            if (!empty($row['help'][1])) $input .= $row['help'][1];
            $element = str_replace("{input}", $input, $this->getTemplate('input', $row['name']));
            if (isset($row['error']) && strpos($element, "{error}") === false)
            {
                if (!stristr($row['error'], "<u>") &&
                    preg_match('/<u>(\w)/i', @$row['label'], $match))
                {
                    $row['error'] = preg_replace('/' . $match[1] . '/i',
                        '<u>\0</u>', $row['error'], 1);
                }
                if (empty($row['error'])) $row['error'] = @$row['label'];
                $row['label'] = "";
            }
            if (!(empty($row['label']) && empty($row['error'])) &&
                strpos($input, "<label") === false &&
                preg_match('/id="([^"]+)/', $input, $mId))
            {
                $labelTag = '<label';
                if (preg_match('/<u>(\w)/i', @$row['label'] . @$row['error'], $mKey))
                {
                    $labelTag .= ' accesskey="' . strtolower($mKey[1]) . '"';
                }
                $labelTag .= ' for="' . $mId[1] . '">';
                $element = str_replace("{label}", $labelTag . "{label}</label>",
                    $element);
            }

            if (isset($row['error']) && strpos($element, "{error}") === false)
            {
                $element = str_replace("{label}", "{error}", $element);
            }
            if (!empty($row['label']) && $this->getTemplate('label', $row['name']))
            {
                $element = str_replace("{label}", $this->getTemplate('label', $row['name']),
                    $element);
            }
            if (!empty($row['error']) && $this->getTemplate('error', $row['name']))
            {
                $element = str_replace("{error}", $this->getTemplate('error', $row['name']),
                    $element);
            }
            if (!empty($row['help'][0]) && $this->getTemplate('help', $row['name']))
            {
                $element = str_replace("{help}", $this->getTemplate('help', $row['name']),
                    $element);
            }

            $elements .= str_replace(
                array("{label}", "{error}", "{help}", "{class}"),
                array(@$row['label'], @$row['error'], @$row['help'][0],
                empty($row['class']) ? "" : ' class="' . $row['class'] . '"'),
                $element);
        }

        if ($this->getTemplate('accesskey'))
        {
            $replacement = str_replace("{accesskey}", '\1', $this->getTemplate('accesskey'));
            $elements = preg_replace('/<u>(\w)<\/u>/i', $replacement, $elements);
        }

        $form .= str_replace("{input}", $elements, $this->getTemplate('form'));
        $form .= '</form>';
        if ((!isset($setFocus) && $this->id == "form") || !empty($setFocus))
        {
            $form .= $this->_getFocusHandler();
        }
        return $form;
    }
    function getTemplate($template, $name = null)
    {
        return empty($this->templates[$template]) ? false : $this->templates[$template];
    }

    function _implodeAttributes(&$attributes)
    {
        if (empty($attributes)) return "";
        $html = "";
        foreach ($attributes as $attribute => $value)
        {
            $html .= ' ' . $attribute . '="' . str_replace('"', '&quot;', $value) . '"';
        }
        return $html;
    }

    function _getFocusHandler()
    {
        $form = "";
        $id = null;
        $count = count($this->_rows);
        for ($i = 0; $i < $count; $i++)
        {
            if ($this->_rows[$i]['type'] != 'text' &&
                $this->_rows[$i]['type'] != 'password' &&
                $this->_rows[$i]['type'] != 'textarea') continue;
            if (!isset($id)) $id = $i;
            if (!empty($this->_rows[$i]['error'])) { $id = $i; break; }
        }
        if (isset($id))
        {
            $form = "<script type=\"text/javascript\">\nself.onload=";
            $form .= "function(){var f=document.forms['" . $this->id . "'];";
            $form .= "if(f){var e=f.elements['" . $this->_rows[$id]['name'];
            $form .= count($this->_rows[$id]['value']) > 1 ? "[]'][0" : "'";
            $form .= "];if(e&&e.focus)e.focus();}}\n</script>";
        }
        return $form;
    }

    function display($setFocus = null)
    {
        echo $this->toHTML($setFocus);
    }
}
