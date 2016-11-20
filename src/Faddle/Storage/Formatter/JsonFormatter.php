<?php namespace Faddle\Storage\Formatter;

/**
 * Encodes/decodes data into JSON
 */
class JsonFormatter implements FormatterInterface {

    /**
     * {@inheritdoc}
     */
    public function encode($data) {
        return json_encode($data);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data) {
        return json_decode($data, true);
    }

}
