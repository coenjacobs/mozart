<?php

namespace CoenJacobs\Mozart\Replace;

class ClassmapReplacer extends BaseReplacer
{
    public function replace( $contents )
    {
        return preg_replace_callback(
            '/(?:\W[abstract]*class |interface )([a-zA-Z\_]+)[ ]*{/U',
            function ($matches) {
                $replace = 'CJDT_' . $matches[1];
                return str_replace($matches[1], $replace, $matches[0]);
            },
            $contents
        );
    }
}