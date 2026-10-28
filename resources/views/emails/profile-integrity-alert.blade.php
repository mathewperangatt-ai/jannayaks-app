Jannayaks — profile integrity monitor (REPORT-ONLY)

This message is an automated founder alert. Nothing was suspended or
unpublished. Review the Admin Panel / database before taking action.

Run ID: {{ $summary['run_id'] }}
Mode: {{ $summary['mode'] }}
Mass-mismatch threshold exceeded: {{ ! empty($summary['mass_mismatch_alert']) ? 'yes' : 'no' }}

Counts
- Published eligible: {{ $summary['profiles_published'] }}
- Scanned: {{ $summary['scanned'] }}
- Healthy: {{ $summary['healthy'] }}
- Authorized change: {{ $summary['authorized_change'] }}
- Integrity mismatch: {{ $summary['integrity_mismatch'] }}
- Missing snapshot: {{ $summary['missing_snapshot'] }}
- Discrepancies: {{ $summary['discrepancies'] }}
- Scan errors: {{ $summary['scan_error'] }}
- New incidents this run: {{ $summary['incidents_created'] }}
- Deep verify %: {{ $summary['deep_verify_percent'] }}
- Duration (s): {{ $summary['duration_seconds'] }}

@if (count($findings) > 0)
Findings (profile id / slug / type — no applicant contact or editorial body)
@foreach ($findings as $finding)
- #{{ $finding['profile_id'] }} {{ $finding['slug'] !== null && $finding['slug'] !== '' ? '/'.$finding['slug'] : '' }} [{{ $finding['type'] }}]{{ ! empty($finding['newly_created']) ? ' (new)' : ' (open)' }}
  {{ $finding['detection_reason'] }}
@endforeach
@else
No per-profile finding rows were attached to this alert.
@endif

— Jannayaks integrity watchdog
