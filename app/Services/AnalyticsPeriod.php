<?php

namespace App\Services;

enum AnalyticsPeriod: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';
}
