<?php

namespace App\Services;

use App\Models\ContactInquiry;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactInquiryExportService
{
    private const HEADERS = [
        'ID',
        'Name',
        'Email',
        'Phone',
        'Subject',
        'Message',
        'Status',
        'IP Address',
        'User Agent',
        'Replied At',
        'Submitted At',
        'Updated At',
    ];

    public function toCsv(Collection $inquiries): StreamedResponse
    {
        $filename = 'contact-messages-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($inquiries): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, self::HEADERS);

            foreach ($inquiries as $inquiry) {
                fputcsv($handle, $this->mapRow($inquiry));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function toExcel(Collection $inquiries): StreamedResponse
    {
        $filename = 'contact-messages-'.now()->format('Y-m-d-His').'.xls';

        return response()->streamDownload(function () use ($inquiries): void {
            echo $this->buildSpreadsheetXml($inquiries);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function mapRow(ContactInquiry $inquiry): array
    {
        return [
            $inquiry->id,
            $inquiry->name,
            $inquiry->email,
            $inquiry->phone ?? '',
            $inquiry->subject ?? '',
            $inquiry->message,
            $inquiry->status->label(),
            $inquiry->ip_address ?? '',
            $inquiry->user_agent ?? '',
            $inquiry->replied_at?->toDateTimeString() ?? '',
            $inquiry->created_at?->toDateTimeString() ?? '',
            $inquiry->updated_at?->toDateTimeString() ?? '',
        ];
    }

    private function buildSpreadsheetXml(Collection $inquiries): string
    {
        $rows = array_map(
            fn (ContactInquiry $inquiry) => $this->mapRow($inquiry),
            $inquiries->all(),
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Worksheet ss:Name="Contact Messages"><Table>';

        $xml .= $this->buildXmlRow(self::HEADERS);

        foreach ($rows as $row) {
            $xml .= $this->buildXmlRow($row);
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return $xml;
    }

    private function buildXmlRow(array $cells): string
    {
        $xml = '<Row>';
        foreach ($cells as $cell) {
            $value = htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= '<Cell><Data ss:Type="String">'.$value.'</Data></Cell>';
        }
        $xml .= '</Row>';

        return $xml;
    }
}
