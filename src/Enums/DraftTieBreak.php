<?php

namespace ApexTechnology\TidyBill\Enums;

enum DraftTieBreak
{
    case Newest;
    case Oldest;
    case Strict;
}
