<?php

namespace CronManager;

trait Debuggable
{
    public function dumpVariables(...$variables)
    {
        echo '<pre>';
        foreach ($variables as $var) {
            print_r($var);
            echo "\n";
        }
        echo '</pre>';
    }
}
