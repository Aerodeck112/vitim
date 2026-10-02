@extends('layouts.app')
@section('title', 'Import contacte')
@section('content')
<div class="head"><div><h1>Import contacte</h1><p>Din Excel: Fișier → Salvare ca → CSV. Prima linie are numele coloanelor.</p></div>
  <a class="btn" href="{{ route('portal.contacts.index', $organization->slug) }}">Înapoi la contacte</a></div>
<div class="card">
  <p class="small muted" style="margin-top:0">Coloane recunoscute: <span class="mono">email</span>, <span class="mono">telefon</span>, <span class="mono">prenume</span>, <span class="mono">nume</span>, <span class="mono">firma</span>. Telefoanele în orice formă (0722 123 456, +40722123456). Contactele existente (același email sau telefon) sunt completate, nu dublate. Maximum 5.000 de rânduri o dată.</p>
  <pre class="code small">prenume;nume;email;telefon
Maria;Popescu;maria@exemplu.ro;0722123456
Ion;Ionescu;;0733111222</pre>
  <form method="post" action="{{ route('portal.contacts.import.store', $organization->slug) }}" enctype="multipart/form-data">
    @csrf
    <div class="fl"><label for="file">Fișierul CSV</label><input id="file" type="file" name="file" accept=".csv,text/csv" required>@error('file')<div class="err">{{ $message }}</div>@enderror</div>
    <h2 style="margin-top:18px">Acord pentru mesaje de marketing (opțional)</h2>
    <p class="small muted" style="margin-top:-6px">Campaniile pleacă doar către contactele care și-au dat acordul pe acel canal (GDPR, Legea 506/2004). Bifează doar dacă ai acordul lor documentat; altfel importă fără bife și cere acordul separat.</p>
    @foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $value => $label)
      <label class="chk"><input type="checkbox" name="consent[]" value="{{ $value }}" @checked(in_array($value, old('consent', []), true))> Au acceptat mesaje de marketing pe {{ $label }}</label>
    @endforeach
    <div class="fl" style="margin-top:10px"><label for="evidence">De unde ai acordul?</label><input id="evidence" type="text" name="evidence" maxlength="300" value="{{ old('evidence') }}" placeholder="ex. formular de abonare de pe site, ianuarie 2024 – prezent">@error('evidence')<div class="err">{{ $message }}</div>@enderror</div>
    <label class="chk"><input type="checkbox" name="declare" value="1"> Confirm că aceste persoane și-au dat acordul și că îl pot dovedi la cerere.</label>@error('declare')<div class="err">{{ $message }}</div>@enderror
    <button class="btn btn-p" type="submit" style="margin-top:12px">Importă</button>
  </form>
</div>
@endsection
