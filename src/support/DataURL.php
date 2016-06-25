<?php
namespace tdhsmith\RuleExtensions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DataURL
{
    protected $parsed = false;

    public $data;
    public $parameters;
    public $mediatype;
    public $URL;

    const DEFAULT_PARAMETERS = ['mediatype' => 'text/plain', 'charset' => 'US-ASCII'];

    public function __construct($URL)
    {
        if (!is_null($URL)) {
            $this->URL = $URL;
            $this->parseInternal();
        }
    }

    public static function fromString($URL)
    {
        return new self($URL);
    }

    protected function parseInternal()
    {
        if (substr($this->URL, 0, 5) !== 'data:') {
            throw new InvalidArgumentException('Data URL must start with the string "data:"');
        }

        $content = explode(',', $value);
        if (count($content) !== 2) {
            throw new InvalidArgumentException('Could not separate data from paramaters; data URL should contain exactly 1 comma');
        }

        $this->parameters = collect(explode(';', $content[0]))->transform(function ($item, $index) {
            if ($index === 0) {
                // the first entry is always the media type
                if ($item === '') {
                    // the default defined by the spec
                    return static::DEFAULT_PARAMETERS;
                }
                if (strpos($item, '/') === false) {
                    throw new InvalidArgumentException('Media types must contain a type & subtype separated by a slash "/"')
                }
                return ['mediatype' => $item];
            }
            if (substr_count($item, '=') !== 0) {
                // the only entry without an equals *should* be the base64 flag
                if ($item === 'base64') {
                    return ['base64' => true];
                } else {
                    throw new InvalidArgumentException('All data URL parameters must have an attribute and a value (except for "base64")');
                }
            }
            list($att, $val) = explode('=', $item);
            return [$att => $val];
        })->collapse();

        $this->mediatype = explode('/', $this->parameters['mediatype']);

        if (array_key_exists('base64', $this->parameters) && $this->parameters['base64'] === true) {
            $this->data = $this->base64url_decode($content[1]);
        } else {
            $this->data = $content[1];
        }

        $this->parsed = true;
        return $this;
    }

    protected function base64url_decode($base64)
    {
        return base64_decode(strtr($base64, '-_', '+/'));
    }

    protected function base64url_encode($plaintext)
    {
        // NOTE: We don't use padding here. Maybe in the future that should be an option?
        return rtrim(strtr(base64_encode($plaintext), '+/', '-_'), '=');
    }

}