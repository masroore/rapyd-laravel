<?php

namespace Zofe\Rapyd;

use Illuminate\Support\Facades\Request;

class Url
{
    public $url;

    protected $semantic = [
        'page', 'orderby',
        'show', 'modify',
        'create', 'insert',
        'update', 'delete',
        'process',
    ];

    public function set($url)
    {
        $this->url = $url;

        return $this;
    }

    public function getArray()
    {
        if (blank($this->url)) {
            $this->url = $this->current();
        }

        parse_str(parse_url($this->url, PHP_URL_QUERY), $params);

        return $params;
    }

    public function current($uri = false)
    {
        return $uri ? Request::url() : Request::fullUrl();
    }

    public function append($key, $value)
    {
        $url = $this->get();
        $qs_array = [];

        if (false !== mb_strpos($url, '?')) {
            $qs = mb_substr($url, mb_strpos($url, '?') + 1);
            $url = mb_substr($url, 0, mb_strpos($url, '?'));

            parse_str($qs, $qs_array);
        }

        $qs_array[$key] = $value;
        $query_string = self::unparse_str($qs_array);
        $this->url = $url . $query_string;

        return $this;
    }

    public function get()
    {
        Rapyd::getContainer('url')->to($this->current());

        if (blank($this->url)) {
            return $this->current();
        }
        $url = $this->url;
        $this->url = '';

        return $url;
    }

    public static function unparse_str($array)
    {
        return '?' . preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', http_build_query($array));
    }

    public function removeAll($cid = null)
    {
        $semantic = array_keys($this->semantic);

        if (isset($cid)) {
            foreach ($semantic as $key) {
                $keys[] = $key . $cid;
            }

            $semantic = $keys;
        }

        return $this->remove($semantic);
    }

    public function remove($keys)
    {
        $qs_array = [];
        $url = $this->get();

        if (false === mb_strpos($url, '?')) {
            $this->url = $url;

            return $this;
        }

        $qs = mb_substr($url, mb_strpos($url, '?') + 1);
        $url = mb_substr($url, 0, mb_strpos($url, '?'));

        parse_str($qs, $qs_array);

        if (!\is_array($keys)) {
            if ('ALL' == $keys) {
                $this->url = $url;

                return $this;
            }

            $keys = [$keys];
        }
        foreach ($keys as $key) {
            unset($qs_array[$key]);
        }
        $query_string = self::unparse_str($qs_array);
        $this->url = $url . $query_string;

        return $this;
    }

    public function replace($key, $newkey)
    {
        $qs_array = [];
        $url = $this->get();

        if (false !== mb_strpos($url, '?')) {
            $qs = mb_substr($url, mb_strpos($url, '?') + 1);
            $url = mb_substr($url, 0, mb_strpos($url, '?'));

            parse_str($qs, $qs_array);
        }

        if (isset($qs_array[$key])) {
            $qs_array[$newkey] = $qs_array[$key];
            unset($qs_array[$key]);
        }

        $query_string = self::unparse_str($qs_array);
        $this->url = $url . $query_string;

        return $this;
    }

    public function value($key, $default = false)
    {
        if (mb_strpos($key, '|')) {
            $keys = explode('|', $key);
            foreach ($keys as $k) {
                $v = $this->value($k, $default);
                if ($v != $default) {
                    return $v;
                }
            }//foreach

            return $default;
        }//if

        parse_str(parse_url($this->current(), PHP_URL_QUERY), $params);
        if (mb_strpos($key, '.')) {
            [$namespace, $subkey] = explode('.', $key);

            return (isset($params[$namespace][$key])) ? $params[$namespace][$key] : $default;
        }

        return (isset($params[$key])) ? $params[$key] : $default;
    }
}
