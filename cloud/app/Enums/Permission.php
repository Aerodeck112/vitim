<?php

declare(strict_types=1);

namespace App\Enums;

/** Acțiunile verificate în portal și în dashboard. Numele sunt folosite și ca Gate-uri. */
enum Permission: string
{
    case ViewReports = 'view_reports';
    case ViewConversations = 'view_conversations';
    case HandleConversations = 'handle_conversations';
    case ManageLeads = 'manage_leads';
    case ManageAgent = 'manage_agent';
    case ManageKnowledge = 'manage_knowledge';
    case ManageSites = 'manage_sites';
    case ManageIntegrations = 'manage_integrations';
    case ManageUsers = 'manage_users';
    case ManageBilling = 'manage_billing';
    case ExportData = 'export_data';
    case DeleteData = 'delete_data';
}
