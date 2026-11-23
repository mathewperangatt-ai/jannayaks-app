Jannayaks Security Administrator — immediate integrity enforcement

A published profile failed integrity verification and has been suspended
from public display. This is an automated enforcement action; manual review
is required before the profile can be restored.

Profile: #{{ $profile->id }} — {{ $profile->slug ?? '(no slug)' }}
Run: {{ $runId }}
Suspended at: {{ now()->timezone('Asia/Kolkata')->format('Y-m-d H:i:s') }} IST

Incidents ({{ count($incidents) }}):
@foreach ($incidents as $incident)
  — Incident #{{ $incident->id }} [{{ $incident->type }}]
    Reason: {{ \Illuminate\Support\Str::limit($incident->detection_reason, 220) }}
    Detected: {{ $incident->detected_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i:s') }} IST
@endforeach

Before/after evidence is preserved in the integrity_incidents table.

NEXT STEPS (manual only — nothing is auto-repaired or auto-republished):
1. Inspect the incidents in the admin dashboard (integrity_incidents, run_id {{ $runId }}).
2. Compare the expected (known-good) and observed payloads.
3. If the change was legitimate, reconcile through the normal editorial workflow.
4. If tampering is confirmed, preserve the evidence and investigate.
5. Restoration of public visibility requires explicit administrator approval
   through the existing integrity-waiver review flow.

This mailbox receives security alerts only. Do not reply.
