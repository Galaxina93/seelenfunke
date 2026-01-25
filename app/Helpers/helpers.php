<?php

function allowed($key):bool
{
    return isset(session("permissions")[$key]);
}
