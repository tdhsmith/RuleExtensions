<?php

// TODO: I think the overall premise of this class is currently
// unicode-UNfriendly, so only expect it to work for "ASCII plus"
// at the moment. Eventually I'll explore this as people need it...
class Alphabet {

    protected $ranges;
    protected $isolates;
    protected $combined;

    const PRESENT_CHARS_AS_KEYS  = 1;
    const UNIQUE_CHARS_AS_STRING = 3;

    public function __construct($characters)
    {
        if (is_array($characters)) {
            $this->set($characters);
        } else {
            $this->reset();
        }
    }

    public function reset()
    {
        $this->ranges   = [];
        $this->isolates = [];
        $this->combined = [];
    }

    public function set(array $characters)
    {
        foreach ($characters as $range) {
            if (is_array($range)) {
                if (count($range) === 2) {
                    if (is_string($range[0]) && is_string($range[1])) {
                        $this->ranges[] = [ord($range[0]), ord($range[1])];
                    } elseif (is_int($range[0]) && is_int($range[1])) {
                        $this->ranges = $range;
                    } else {
                        throw new InvalidArgumentException('Alphabet ranges must be strings or ints');
                    }
                } else {
                    throw new InvalidArgumentException('Alphabet ranges must be pairs');
                }
            } elseif (is_string($range)) {
                if (length($range) === 1) {
                    $this->isolates[] = ord($range);
                } else {
                    // TODO: setter for string-style "a-z"?
                    throw new InvalidArgumentException('Alphabet characters must be length-1 strings');
                }
            } elseif (is_int($range)) {
                // TODO: bother with checking the value? (ie between 0 and 255)
                $this->isolates[] = $range;
            } else {
                throw new InvalidArgumentException('Alphabet config must only contain strings, integers, and subarrays');
            }
        }
        $this->generateCombined();
        // TODO: check for overlap / simplify etc
    }

    protected function generateCombined()
    {
        $this->combined = [];
        foreach($this->ranges as $range) {
            for ($i = $range[0]; $i <= $range[1]; $i++) {
                if ($i > 255) {
                    throw new InvalidArgumentException('Iterated beyond 255; range values must be ill-formed: [' . $range[0] . ',' . $range[1] . ']');
                }
                $this->combined[$i] = true;
            }
        }
        foreach($this->isolates as $isolate) {
            $this->combined[$isolate] = true;
        }
    }

    public function get()
    {
        return $this->ranges +  $this->isolates;
    }

    public function hasWord($word)
    {
        return count($this->nonalphabetic($word)) === 0;
    }

    public function nonalphabetic($word)
    {
        $charIndex = count_chars($word, self::PRESENT_CHARS_AS_KEYS);
        return array_diff_key($charIndex, $this->combined);
    }

    // Alias
    public function hasString($str)
    {
        return $this->hasWord($str);
    }

}