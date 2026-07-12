<?php

namespace ApexTechnology\TidyBill\Enums;

enum EInvoiceFormat: string
{
    case ZugferdEn16931 = 'zugferd_en16931';
    case UblPeppolBis3 = 'ubl_peppol_bis3';
}
