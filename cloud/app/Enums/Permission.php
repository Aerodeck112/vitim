<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Acțiunile verificate în portal, în API și în dashboard. Numele sunt folosite și ca Gate-uri
 * (`can:<valoare>`), deci verificările nu se scriu niciodată ca `if ($role === ...)` prin controllere.
 * Permisiunile modulelor viitoare (inbox, campanii, automatizări) se adaugă aici, apoi în matricea din OrgRole.
 */
enum Permission: string
{
    case ViewReports = 'view_reports';
    case ViewContacts = 'view_contacts';
    case ManageContacts = 'manage_contacts';
    case ManageConsent = 'manage_consent';
    case ViewLeads = 'view_leads';
    case ManageLeads = 'manage_leads';
    case ViewConversations = 'view_conversations';
    case HandleConversations = 'handle_conversations';
    case ManageCampaigns = 'manage_campaigns';
    case ManageAgents = 'manage_agents';
    case ManageKnowledge = 'manage_knowledge';
    case ManageSites = 'manage_sites';
    case ManageIntegrations = 'manage_integrations';
    case ManageUsers = 'manage_users';
    case ManageOrganization = 'manage_organization';
    case ManageBilling = 'manage_billing';
    case ViewAudit = 'view_audit';
    case ExportData = 'export_data';
    case DeleteData = 'delete_data';
}
