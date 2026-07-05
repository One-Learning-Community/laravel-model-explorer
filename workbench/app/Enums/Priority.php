<?php

namespace Workbench\App\Enums;

/**
 * Int-backed enum — exercises enum-cast expansion where the backing value is an
 * integer (must serialize as 1, not "1"), complementing the string-backed
 * PostStatus fixture.
 */
enum Priority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
}
