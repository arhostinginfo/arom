{{--
  Contacts / POC of a client, on the enquiry and prospect pages. $box from ContactController::box(): the first
  ContactController::BOX_SIZE contacts by name, whether there are more, and the client filter for the links.
--}}
@if ($box)
@php($cps = app(\App\Services\PageSetupService::class)->form('contact'))
<div class="sectcard" id="contacts">
  <div class="sh">
    <h2>Contacts / POC</h2><span class="sp"></span>
    <a class="btn gh sm" href="{{ route('crm.contacts.index', $box['params']) }}">All contacts of this client</a>
    @can('contact.add')<a class="btn add sm" href="{{ route('crm.contacts.create', $box['params'] + ['from' => $box['from']]) }}"><svg class="ico"><use href="#i-plus"/></svg>Add contact</a>@endcan
  </div>
  <div class="sb" style="padding-top:6px">
    @if ($box['list']->isEmpty())
      <div class="empty">No contacts for this client yet.</div>
    @else
      <div class="tablecard"><table class="atable rtable">
        <thead><tr><th>Name</th>@if ($cps->show('designation'))<th>Designation</th>@endif @if ($cps->show('mobile'))<th>Mobile</th>@endif @if ($cps->show('email'))<th>Email</th>@endif @if ($cps->show('roles'))<th>Roles</th>@endif</tr></thead>
        <tbody>
        @foreach ($box['list'] as $c)
          @php($open = route('crm.contacts.show', ['contact' => $c, 'from' => $box['from']]))
          <tr class="clk" onclick="location.href='{{ $open }}'">
            <td data-label="Name" class="wide"><a class="rowlink" href="{{ $open }}"><b>{{ $c->name }}</b></a> <span class="cap muted">{{ $c->contact_no }}</span></td>
            @if ($cps->show('designation'))<td data-label="Designation">{{ $c->designation }}</td>@endif
            @if ($cps->show('mobile'))<td data-label="Mobile" class="num">{{ $c->mobile }}</td>@endif
            @if ($cps->show('email'))<td data-label="Email">{{ $c->email }}</td>@endif
            @if ($cps->show('roles'))<td data-label="Roles">{{ $c->roleNames() }}</td>@endif
          </tr>
        @endforeach
        </tbody>
      </table></div>
    @endif
  </div>
</div>
@endif
