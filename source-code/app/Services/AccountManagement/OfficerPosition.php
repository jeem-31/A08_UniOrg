<?php

namespace App\Services\AccountManagement;

enum OfficerPosition: string implements OrganizationPosition
{
    case President = 'President';
    case VicePresident = 'Vice President';
    case Secretary = 'Secretary';
    case Treasurer = 'Treasurer';
    case Auditor = 'Auditor';
    case FirstYearRepresentative = '1st Year Representative';
    case SecondYearRepresentative = '2nd Year Representative';
    case ThirdYearRepresentative = '3rd Year Representative';
    case FourthYearRepresentative = '4th Year Representative';
    case BusinessManager = 'Business Manager';
    case PublicInformationOfficer = 'Public Information Officer';
    case EventCoordinator = 'Event Coordinator';
    case Comelec = 'COMELEC';

    public function routeValue(): string
    {
        return match ($this) {
            self::President => 'president', self::VicePresident => 'vice-president',
            self::Secretary => 'secretary',
            self::Treasurer => 'treasurer', self::Auditor => 'auditor',
            self::FirstYearRepresentative => '1st-year-representative',
            self::SecondYearRepresentative => '2nd-year-representative',
            self::ThirdYearRepresentative => '3rd-year-representative',
            self::FourthYearRepresentative => '4th-year-representative',
            self::BusinessManager => 'business-manager',
            self::PublicInformationOfficer => 'public-information-officer',
            self::EventCoordinator => 'event-coordinator',
            self::Comelec => 'comelec',
        };
    }

    public function role(): string
    {
        return $this->value;
    }
}
