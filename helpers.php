<?php


/**
 * Help functions
 */
function print_pre($obj, $title = '')
{
    echo '<h3>' . $title . '</h3>';
    echo '<pre style="padding:10px;background:#33384b;width: 100%;color: #ffffff">';
    print_r($obj);
    echo '</pre>';
}

function br($text = '', $horizontal_space = 0) {
    $i = $horizontal_space;
    do {
        echo '<br>';
        $i--;
    } while ($i > 0);

    if ($text) {
        echo '<h1 class="debug_text" style="font-size:24px;color:red;">' . $text . '</h1>';
        echo '<br>';
    }
}