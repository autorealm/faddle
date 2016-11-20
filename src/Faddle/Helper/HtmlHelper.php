<?php namespace Faddle\Helper;

/**
 * Html
 * functional usage for generate HTML code
 *
 * @package Faddle\Helper
 */
class HtmlHelper {

    /**
     * @var array Macros
     */
    public static $macros = array();

    /**
     * @var string Encode for HTML
     */
    protected static $charset = 'utf-8';

    /**
     * @var array Allow self close tags
     */
    protected static $self_close_tags = array(
        'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'
    );

    /**
     * Covert to entitles
     *
     * @param string $value
     * @return string
     */
    public static function encode($value)
    {
        return htmlentities($value, ENT_QUOTES, static::charset(), false);
    }

    /**
     * Convert entities to HTML characters.
     *
     * @param  string $value
     * @return string
     */
    public static function decode($value)
    {
        return html_entity_decode($value, ENT_QUOTES, static::charset());
    }

    /**
     * Convert HTML special characters.
     *
     * @param  string $value
     * @return string
     */
    public static function specialChars($value)
    {
        return htmlspecialchars($value, ENT_QUOTES, static::charset(), false);
    }

    /**
     * Get or set macro
     *
     * @param string          $key
     * @param string|\Closure $html
     * @return mixed
     */
    public static function macro($key, $html = null)
    {
        if ($html == null) {
            return isset(self::$macros[$key]) ? (is_callable(self::$macros[$key]) ? call_user_func(self::$macros[$key]) : self::$macros[$key]) : null;
        }

        return self::$macros[$key] = $html;
    }

    /**
     * Build a link
     *
     * @param string $src
     * @param string $text
     * @param array  $attributes
     * @return string
     */
    public static function a($src, $text, array $attributes = array())
    {
        return self::dom('a', $text, array('href' => Url::to($src)) + $attributes);
    }

    /**
     * Build a script
     *
     * @param string $src
     * @param array  $attributes
     * @return string
     */
    public static function script($src, array $attributes = array())
    {
        return self::dom('script', '', ($src ? array('src' => Url::asset($src)) : array()) + $attributes + array('type' => 'text/javascript'));
    }

    /**
     * Build a link
     *
     * @param string $src
     * @param string $rel
     * @param array  $attributes
     * @return string
     */
    public static function link($src, $rel = 'stylesheet', array $attributes = array())
    {
        return self::dom('link', array('rel' => $rel, 'href' => Url::asset($src)) + $attributes);
    }

    /**
     * Alias img
     *
     * @param string $src
     * @param array  $attributes
     * @return string
     */
    public static function image($src, array $attributes = array())
    {
        return self::img($src, $attributes);
    }

    /**
     * Build a image
     *
     * @param string $src
     * @param array  $attributes
     * @return string
     */
    public static function img($src, array $attributes = array())
    {
        return self::dom('img', array('src' => Url::asset($src)) + $attributes);
    }

    /**
     * A simply style link
     *
     * @param string $src
     * @return string
     */
    public static function style($src)
    {
        return self::link($src);
    }

    /**
     * Select options
     *
     * @param string $name
     * @param array  $options
     * @param string $selected
     * @param array  $attributes
     * @return string
     */
    public static function select($name, array $options, $selected = null, array $attributes = array())
    {
        $options_html = array();
        foreach ($options as $key => $value) {
            $attr = array('value' => $key);
            if ($key == $selected) {
                $attr['selected'] = 'true';
            }
            $options_html[] = self::dom('option', $value, $attr);
        }
        return self::dom('select', implode('', $options_html), array('name' => $name) + $attributes);
    }

    /**
     * Checkbox
     *
     * @param string $name
     * @param string $value
     * @param bool   $check
     * @param array  $attributes
     * @return string
     */
    public static function checkbox($name, $value, $check = false, $attributes = null)
    {
        return self::dom('input', ($check ? array('checked' => 'true') : array()) + (array)$attributes + array('name' => $name, 'value' => $value));
    }

    /**
     * Radio
     *
     * @param string $name
     * @param string $value
     * @param bool   $check
     * @param array  $attributes
     * @return string
     */
    public static function radio($name, $value, $check = false, $attributes = null)
    {
        return self::dom('input', ($check ? array('checked' => 'true') : array()) + (array)$attributes + array('name' => $name, 'value' => $value));
    }

    /**
     * Checkboxes
     *
     * @param string         $name
     * @param array          $values
     * @param null|string    $checked
     * @param array          $attributes
     * @param array|callable $wrapper
     * @return string
     */
    public static function checkboxes($name, array $values, $checked = null, $attributes = null, $wrapper = null)
    {
        return self::inputGroup($name, 'checkbox', $values, $checked, $attributes, $wrapper);
    }

    /**
     * Radios
     *
     * @param string         $name
     * @param array          $values
     * @param null|string    $checked
     * @param array          $attributes
     * @param array|callable $wrapper
     * @return string
     */
    public static function radios($name, array $values, $checked = null, $attributes = null, $wrapper = null)
    {
        return self::inputGroup($name, 'radio', $values, $checked, $attributes, $wrapper);
    }

    /**
     * Input group
     *
     * @param string         $name
     * @param string         $type
     * @param array          $values
     * @param null|string    $checked
     * @param array          $attributes
     * @param array|callable $wrapper
     * @return string
     */
    public static function inputGroup($name, $type, array $values, $checked = null, $attributes = null, $wrapper = null)
    {
        return self::loop($values, function ($key, $value) use (
            $name, $type, $checked, $attributes, $wrapper
        ) {
            $attr = array('value' => $key, 'type' => $type, 'name' => $name) + (array)$attributes;
            if ($checked) {
                if (is_array($checked) && in_array($key, $checked)
                    || $checked == $key
                ) {
                    $attr['checked'] = 'true';
                }
            }
            if ($wrapper) {
                if (is_array($wrapper)) {
                    return static::dom($wrapper[0], static::dom('input', $value, $attr), isset($wrapper[1]) ? (array)$wrapper[1] : null);
                } else if (is_callable($wrapper)) {
                    return $wrapper(static::dom('input', $value, $attr));
                }
                return null;
            } else {
                return static::dom('input', $value, $attr);
            }
        });
    }

    /**
     * Repeat elements
     *
     * @param string $dom
     * @param array  $values
     * @param array  $attributes
     * @return string
     */
    public static function repeat($dom, array $values, array $attributes = null)
    {
        return self::loop($values, function ($value) use ($dom, $attributes) {
            return static::dom($dom, $value, $attributes);
        });
    }

    /**
     * Loop and join as string
     *
     * @param array    $values
     * @param \Closure $mapper
     * @return string
     */
    public static function loop(array $values, \Closure $mapper)
    {
        $html = array();
        foreach ($values as $key => $value) {
            $html[] = $mapper($value, $key);
        }
        return implode('', $html);
    }

    /**
     * Build a element
     *
     * @param string $name
     * @param string $text
     * @param array  $attributes
     * @return string
     */
    public static function dom($name, $text = '', array $attributes = null)
    {
        $self_close = false;
        if (in_array($name, self::$self_close_tags)) {
            $self_close = true;
        }

        if ($self_close && is_array($text)) {
            $attributes = $text;
            $text = '';
        }

        $attr = '';
        $attributes = (array)$attributes;
        foreach ($attributes as $k => $v) {
            if (is_numeric($k)) $k = $v;

            if (!is_null($v)) {
                $attr .= ' ' . $k . '="' . static::encode($v) . '"';
            }
        }

        return '<' . $name . $attr .
        ($self_close ? ' />' : '>') . $text .
        ((!$self_close) ? '</' . $name . '>' : '');
    }

    /**
     * Get current encoding
     *
     * @return mixed
     */
    protected static function charset()
    {
        return static::$charset ? : static::$charset = 'utf-8';
    }


	/**
	 * Open HTML tag
	 *
	 * @access public
	 * @param string $name Tag name
	 * @param array $attributes Array of tag attributes
	 * @param boolean $empty If tag is empty it will be automaticly closed
	 * @return string
	 */
	public static function open_html_tag($name, $attributes = null, $empty = false) {
		$attribute_string = '';
		if (is_array($attributes) && count($attributes)) {
			$prepared_attributes = array();
			foreach ($attributes as $k => $v) {
				if (trim($k) <> '') {
					if (is_bool($v)) {
						if ($v) {
							$prepared_attributes[] = "$k=\"$k\"";
						}
					} else {
						$prepared_attributes[] = $k . '="' . clean($v) . '"';
					} // if
				} // if
			} // foreach
			$attribute_string = implode(' ', $prepared_attributes);
		} // if
		$empty_string = $empty ? ' /' : ''; // Close?
		return "<$name $attribute_string$empty_string>"; // And done...
	} // html_tag

	/**
	 * Close specific HTML tag
	 *
	 * @access public
	 * @param string $name Tag name
	 * @return string
	 */
	public static function close_html_tag($name) {
		return "</$name>";
	} // close_html_tag

	/**
	 * Return title tag
	 *
	 * @access public
	 * @param string $title
	 * @return string
	 */
	public static function title_tag($title) {
		return static::open_html_tag('title') . $title . static::close_html_tag('title');
	} // title_tag

	/**
	 * Prepare link tag
	 *
	 * @access public
	 * @param string $href
	 * @param string $rel_or_rev Rel or rev
	 * @param string $rel
	 * @param array $attributes
	 * @return string
	 */
	public static function link_tag($href, $rel_or_rev = 'rel', $rel = 'alternate', $attributes = null) {
		// Prepare attributes
		$all_attributes = array('href' => $href, $rel_or_rev => $rel); // array
		// Additional attributes
		if (is_array($attributes) && count($attributes)) {
			$all_attributes = array_merge($all_attributes, $attributes);
		} // if
		// And done!
		return static::open_html_tag('link', $all_attributes, true);
	} // link_tag

	/**
	 * Rel link tag
	 *
	 * @access public
	 * @param string $href
	 * @param string $rel
	 * @param string $attributes
	 * @return string
	 */
	public static function link_tag_rel($href, $rel, $attributes = null) {
		return static::link_tag($href, 'rel', $rel, $attributes);
	} // link_tag_rel

	/**
	 * Rev link tag
	 *
	 * @access public
	 * @param string $href
	 * @param string $rel
	 * @param string $attributes
	 * @return string
	 */
	public static function link_tag_rev($href, $rel, $attributes = null) {
		return static::link_tag($href, 'rev', $rel, $attributes);
	} // link_tag_rev

	/**
	 * Return code of meta tag
	 *
	 * @access public
	 * @param string $name Name of the meta property
	 * @param string $content
	 * @param boolean $http_equiv
	 * @return string
	 */
	public static function meta_tag($name, $content, $http_equiv = false) {
		// Name attribute
		$name_attribute = $http_equiv ? 'http-equiv' : 'name';
		// Prepare attributes
		$attributes = array($name_attribute => $name, 'content' => $content); // array
		// And done...
		return static::open_html_tag('meta', $attributes, true);
	} // meta_tag

	/**
	 * Generate javascript tag
	 *
	 * @access public
	 * @param string $src Path to external file
	 * @param string $content Tag content
	 * @return string
	 */
	public static function javascript_tag($src = null, $content = null) {
		// Content formatting
		if ($content) {
			$content = "\n" . $content . "\n";
		}
		// Prepare attributes
		$attributes = array('type' => 'text/javascript');
		if (!is_null($src)) {
			$attributes['src'] = $src;
		} // if
		// Generate
		return static::open_html_tag('script', $attributes) . $content . static::close_html_tag('script');
	} // javascript_tag

	/**
	 * Render stylesheet tag
	 *
	 * @access public
	 * @param string $href URL of external stylesheet
	 * @Param array $attributes
	 * @return string
	 */
	public static function stylesheet_tag($href, $attributes = null) {
		$all_attributes = array('type' => 'text/css'); // array
		if (is_array($attributes) && count($attributes)) {
			array_merge($all_attributes, $attributes);
		} // if
		return static::link_tag($href, 'rel', 'Stylesheet', $all_attributes);
	} // stylesheet_tag

	/**
	 * Render style tag inside optional conditional comment
	 *
	 * @access public
	 * @param string $content
	 * @param string $condition Condition for conditional comment (IE, lte IE6...). If null
	 *   conditional comment will not be added
	 * @return string
	 */
	public static function style_tag($content, $condition = null) {
		// Open and close for conditional comment
		$open = '';
		$close = '';
		if ($condition) {
			$open = "<!--[if $condition]>\n";
			$close = '<![endif]-->';
		} // if
		// And return...
		return $open . static::open_html_tag('style', array('type' => 'text/css')) . "\n" . $content . "\n" .
			static::close_html_tag('style') . "\n" . $close;
	} // style_tag

	/**
	 * Style class for sequence number
	 *
	 * @access public
	 * @param integer $seq_num
	 * @return string
	 */
	public static function odd_even_class(&$seq_num) {
		(isset($seq_num)) ? $seq_num++ : $seq_num = 1;
		return (($seq_num % 2) == 0) ? 'even' : 'odd';
	}


	/**
	 * Render form label element
	 *
	 * @param void
	 * @return null
	 */
	public static function label_tag($text, $for = null, $is_required = false, $attributes = null, $after_label =
		':') {
		if (trim($for)) {
			if (is_array($attributes)) {
				$attributes['for'] = trim($for);
			} else {
				$attributes = array('for' => trim($for));
			} // if
		} // if
		$render_text = trim($text) . $after_label;
		if ($is_required) {
			$render_text .= ' <span class="label_required">*</span>';
		}
		return static::open_html_tag('label', $attributes) . $render_text . static::close_html_tag('label');
	} // form_label

	/**
	 * Render input field
	 *
	 * @access public
	 * @param string $name Field name
	 * @param mixed $value Field value. Default is NULL
	 * @param array $attributes Additional field attributes
	 * @return null
	 */
	public static function input_field($name, $value = null, $attributes = null) {
		$field_attributes = is_array($attributes) ? $attributes : array();
		$field_attributes['name'] = $name;
		$field_attributes['value'] = $value;
		return static::open_html_tag('input', $field_attributes, true);
	} // input_field

	/**
	 * Render text field
	 *
	 * @access public
	 * @param string $name
	 * @param mixed $value
	 * @param array $attributes Array of additional attributes
	 * @return string
	 */
	public static function text_field($name, $value = null, $attributes = null) {
		// If we don't have type attribute set it
		if (array_key_exists('type', $attributes)) {
			if (is_array($attributes)) {
				$attributes['type'] = 'text';
			} else {
				$attributes = array('type' => 'text');
			} // if
		} // if
		// And done!
		return static::input_field($name, $value, $attributes);
	} // text_field

	/**
	 * Return password field
	 *
	 * @access public
	 * @param string $name
	 * @param mixed $value
	 * @param array $attributes
	 * @return string
	 */
	public static function password_field($name, $value = null, $attributes = null) {
		// Set type to password
		if (is_array($attributes)) {
			$attributes['type'] = 'password';
		} else {
			$attributes = array('type' => 'password');
		} // if
		// Return text field
		return static::text_field($name, $value, $attributes);
	} // password_filed

	/**
	 * Return file field
	 *
	 * @access public
	 * @param string $name
	 * @param mixed $value
	 * @param array $attributes
	 * @return string
	 */
	public static function file_field($name, $value = null, $attributes = null) {
		// Set type to password
		if (is_array($attributes)) {
			$attributes['type'] = 'file';
		} else {
			$attributes = array('type' => 'file');
		} // if
		// Return text field
		return static::text_field($name, $value, $attributes);
	} // file_field

	/**
	 * Render radio field
	 *
	 * @access public
	 * @param string $name Field name
	 * @param mixed $value
	 * @param boolean $checked
	 * @param array $attributes Additional attributes
	 * @return string
	 */
	public static function radio_field($name, $checked = false, $attributes = null) {
		// Prepare attributes array
		if (is_array($attributes)) {
			$attributes['type'] = 'radio';
			if (!isset($attributes['class'])) {
				$attributes['class'] = 'checkbox';
			}
		} else {
			$attributes = array('type' => 'radio', 'class' => 'checkbox');
		} // if
		// Value
		$value = isset($attributes['value']) ? $attributes['value'] : false;
		if ($value === false) {
			$value = 'checked';
		}
		// Checked
		if ($checked) {
			$attributes['checked'] = 'checked';
		} else {
			if (isset($attributes['checked'])) {
				unset($attributes['checked']);
			}
		} // if
		// And done
		return static::input_field($name, $value, $attributes);
	} // radio_field

	/**
	 * Render checkbox field
	 *
	 * @access public
	 * @param string $name Field name
	 * @param mixed $value
	 * @param boolean $checked Checked?
	 * @param array $attributes Additional attributes
	 * @return string
	 */
	public static function checkbox_field($name, $checked = false, $attributes = null) {
		// Prepare attributes array
		if (is_array($attributes)) {
			$attributes['type'] = 'checkbox';
			if (!isset($attributes['class'])) {
				$attributes['class'] = 'checkbox';
			}
		} else {
			$attributes = array('type' => 'checkbox', 'class' => 'checkbox');
		} // if
		// Value
		$value = isset($attributes['value']) ? $attributes['value'] : false;
		if ($value === false) {
			$value = 'checked';
		}
		// Checked
		if ($checked) {
			$attributes['checked'] = 'checked';
		} else {
			if (isset($attributes['checked'])) {
				unset($attributes['checked']);
			}
		} // if
		// And done
		return static::input_field($name, $value, $attributes);
	} // checkbox_field

	/**
	 * This helper will render select list box. Options is array of already rendered option tags
	 *
	 * @access public
	 * @param string $name
	 * @param array $options Array of already rendered option tags
	 * @param array $attributes Additional attributes
	 * @return null
	 */
	public static function select_box($name, $options, $attributes = null) {
		if (is_array($attributes)) {
			$attributes['name'] = $name;
		} else {
			$attributes = array('name' => $name);
		} // if
		$output = static::open_html_tag('select', $attributes) . "\n";
		if (is_array($options)) {
			foreach ($options as $option) {
				$output .= $option . "\n";
			} // foreach
		} // if
		return $output . static::close_html_tag('select') . "\n";
	} // select_box

	/**
	 * Render option tag
	 *
	 * @access public
	 * @param string $text Option text
	 * @param mixed $value Option value
	 * @param array $attributes
	 * @return string
	 */
	public static function option_tag($text, $value = null, $attributes = null) {
		if (!($value === null)) {
			if (is_array($attributes)) {
				$attributes['value'] = $value;
			} else {
				$attributes = array('value' => $value);
			} // if
		} // if
		return static::open_html_tag('option', $attributes) . clean($text) . static::close_html_tag('option');
	} // option_tag

	/**
	 * Render option group
	 *
	 * @param string $label Group label
	 * @param array $options
	 * @param array $attributes
	 * @return string
	 */
	public static function option_group_tag($label, $options, $attributes = null) {
		if (is_array($attributes)) {
			$attributes['label'] = $label;
		} else {
			$attributes = array('label' => $label);
		} // if
		$output = static::open_html_tag('optgroup', $attributes) . "\n";
		if (is_array($options)) {
			foreach ($options as $option) {
				$output .= $option . "\n";
			} // foreach
		} // if
		return $output . static::close_html_tag('optgroup') . "\n";
	} // option_group_tag

	/**
	 * Render submit button
	 *
	 * @access public
	 * @param string $this Button title
	 * @param string $accesskey Accesskey. If NULL accesskey will be skipped
	 * @param array $attributes Array of additinal attributes
	 * @return string
	 */
	public static function submit_button($title, $accesskey = 's', $attributes = null) {
		if (!is_array($attributes)) {
			$attributes = array();
		} // if
		$attributes['class'] = 'submit';
		$attributes['type'] = 'submit';
		$attributes['accesskey'] = $accesskey;
		if ($accesskey) {
			if (strpos($title, $accesskey) !== false) {
				$title = str_replace($accesskey, "<u>$accesskey</u>", $title, 1);
			} // if
		} // if
		return static::open_html_tag('button', $attributes) . $title . static::close_html_tag('button');
	} // submit_button


}