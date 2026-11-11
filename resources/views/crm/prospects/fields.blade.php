{{-- Prospect fields (workbook CRM tab), used on the Prospect page and in the Add prospect drawer. $px prefixes the ids.
     Which fields show and which are mandatory comes from Admin > Page setup. --}}
@php($px = $px ?? '')
@php($ps = app(\App\Services\PageSetupService::class)->form('prospect'))
@php($val = fn ($f) => $px === '' ? old($f, $p->{$f} instanceof \BackedEnum ? $p->{$f}->value : $p->{$f}) : '')
<div class="fgrid">
  <div class="field">
    <label for="{{ $px }}name">Prospect name<span class="req">*</span></label>
    <input class="inp @if ($px === '') @error('name') err @enderror @endif" id="{{ $px }}name" name="name" value="{{ $val('name') }}" maxlength="150" required autocomplete="off">
    @if ($px === '') @include('partials.ferr', ['name' => 'name']) @endif
  </div>
  <div class="field">
    <label for="{{ $px }}market">Domestic / Export<span class="req">*</span></label>
    <select class="inp @if ($px === '') @error('market') err @enderror @endif" id="{{ $px }}market" name="market" required>
      @foreach (\App\Enums\Market::values() as $m)<option value="{{ $m }}" @selected(($val('market') ?: 'Domestic') === $m)>{{ $m }}</option>@endforeach
    </select>
    @if ($px === '') @include('partials.ferr', ['name' => 'market']) @endif
  </div>
  @if ($ps->show('contact_person'))
  <div class="field">
    <label for="{{ $px }}contact_person">Contact person{{ $ps->star('contact_person') }}</label>
    <input class="inp @if ($px === '') @error('contact_person') err @enderror @endif" id="{{ $px }}contact_person" @required($ps->req('contact_person')) name="contact_person" value="{{ $val('contact_person') }}" maxlength="100">
    @if ($px === '') @include('partials.ferr', ['name' => 'contact_person']) @endif
  </div>
  @endif
  @if ($ps->show('position'))
  <div class="field">
    <label for="{{ $px }}position">Position{{ $ps->star('position') }}</label>
    <input class="inp @if ($px === '') @error('position') err @enderror @endif" id="{{ $px }}position" @required($ps->req('position')) name="position" value="{{ $val('position') }}" maxlength="100">
    @if ($px === '') @include('partials.ferr', ['name' => 'position']) @endif
  </div>
  @endif
  <div class="field">
    <label for="{{ $px }}phone_1">Contact no 1<span class="req">*</span></label>
    <input class="inp @if ($px === '') @error('phone_1') err @enderror @endif" id="{{ $px }}phone_1" name="phone_1" value="{{ $val('phone_1') }}" inputmode="tel" maxlength="20" required placeholder="+91 98220 00001">
    @if ($px === '') @include('partials.ferr', ['name' => 'phone_1']) @endif
  </div>
  @if ($ps->show('phone_2'))
  <div class="field">
    <label for="{{ $px }}phone_2">Contact no 2{{ $ps->star('phone_2') }}</label>
    <input class="inp @if ($px === '') @error('phone_2') err @enderror @endif" id="{{ $px }}phone_2" @required($ps->req('phone_2')) name="phone_2" value="{{ $val('phone_2') }}" inputmode="tel" maxlength="20">
    @if ($px === '') @include('partials.ferr', ['name' => 'phone_2']) @endif
  </div>
  @endif
  @if ($ps->show('email'))
  <div class="field">
    <label for="{{ $px }}email">Email{{ $ps->star('email') }}</label>
    <input class="inp @if ($px === '') @error('email') err @enderror @endif" id="{{ $px }}email" @required($ps->req('email')) name="email" type="email" inputmode="email" value="{{ $val('email') }}" maxlength="191">
    @if ($px === '') @include('partials.ferr', ['name' => 'email']) @endif
  </div>
  @endif
  @if ($ps->show('gstin'))
  <div class="field">
    <label for="{{ $px }}gstin">GSTIN{{ $ps->star('gstin') }}</label>
    <input class="inp @if ($px === '') @error('gstin') err @enderror @endif" id="{{ $px }}gstin" @required($ps->req('gstin')) name="gstin" value="{{ $val('gstin') }}" maxlength="15" style="text-transform:uppercase">
    @if ($px === '') @include('partials.ferr', ['name' => 'gstin']) @endif
  </div>
  @endif
  @if ($ps->show('industry_type'))
  <div class="field">
    <label for="{{ $px }}industry_type">Industry type{{ $ps->star('industry_type') }}</label>
    <input class="inp @if ($px === '') @error('industry_type') err @enderror @endif" id="{{ $px }}industry_type" @required($ps->req('industry_type')) name="industry_type" value="{{ $val('industry_type') }}" maxlength="100">
    @if ($px === '') @include('partials.ferr', ['name' => 'industry_type']) @endif
  </div>
  @endif
  @if ($ps->show('address'))
  <div class="field" style="grid-column:1/-1">
    <label for="{{ $px }}address">Address{{ $ps->star('address') }}</label>
    <textarea class="inp @if ($px === '') @error('address') err @enderror @endif" id="{{ $px }}address" @required($ps->req('address')) name="address" rows="2" maxlength="1000" style="height:auto;padding-top:8px">{{ $val('address') }}</textarea>
    @if ($px === '') @include('partials.ferr', ['name' => 'address']) @endif
  </div>
  @endif
</div>
<div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() }}</span></div>
