@php
    use App\Domain\Accounting\Services\Reports\DocumentKindClassifier;
    $kind = DocumentKindClassifier::KIND_BKM;
@endphp
@include('reports.pdf.cash_evidence._document')
