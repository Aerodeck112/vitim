<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadIntent: string
{
    case BuyIntent = 'buy_intent';
    case QuoteRequest = 'quote_request';
    case Appointment = 'appointment';
    case Support = 'support';
    case ProductInformation = 'product_information';
    case Complaint = 'complaint';
    case HumanRequest = 'human_request';
    case Other = 'other';
}
