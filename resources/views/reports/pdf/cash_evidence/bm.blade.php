@php
    use App\Domain\Accounting\Services\Reports\DocumentKindClassifier;
    $kind = DocumentKindClassifier::KIND_BM;
@endphp
@include('reports.pdf.cash_evidence._document')
