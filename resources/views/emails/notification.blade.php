@extends('emails.layout')

@php
    $primary = $brand['primary_color'];
@endphp

@section('content')
    @if ($greetingName)
        <p style="margin:0 0 16px 0; font-size:15px; color:#334155;">Halo <strong>{{ $greetingName }}</strong>,</p>
    @endif

    <h1 style="margin:0 0 16px 0; font-size:20px; line-height:28px; font-weight:700; color:#0f172a;">{{ $heading }}</h1>

    @foreach ($paragraphs as $paragraph)
        <p style="margin:0 0 14px 0; font-size:15px; line-height:24px; color:#475569;">{{ $paragraph }}</p>
    @endforeach

    @if (! empty($details))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0; border-collapse:collapse; background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
            @foreach ($details as $label => $value)
                <tr>
                    <td style="padding:10px 16px; font-size:13px; color:#64748b; border-bottom:{{ $loop->last ? 'none' : '1px solid #e2e8f0' }};">{{ $label }}</td>
                    <td align="right" style="padding:10px 16px; font-size:13px; font-weight:600; color:#1e293b; border-bottom:{{ $loop->last ? 'none' : '1px solid #e2e8f0' }};">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($action)
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 8px 0;">
            <tr>
                <td style="border-radius:8px; background-color:{{ $primary }};">
                    <a href="{{ $action['url'] }}" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px;">{{ $action['label'] }}</a>
                </td>
            </tr>
        </table>
    @endif
@endsection
