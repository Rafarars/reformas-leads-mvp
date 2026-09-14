<?php

namespace Reformas\Leads\Lead\Domain;

interface LeadRepository
{
    // Guarda el lead y las entradas nuevas de su historial.
    public function save(Lead $lead): void;

    public function search(LeadId $id): ?Lead;

    // Mismo teléfono O mismo email: cualquiera de los dos identifica a la persona.
    public function searchByContact(PhoneNumber $phone, EmailAddress $email): ?Lead;

    /**
     * @return list<LeadHistoryEntry>
     */
    public function history(LeadId $id): array;
}
