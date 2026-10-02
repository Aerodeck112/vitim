@if ($user->last_login_at)
  <span class="muted">{{ $user->last_login_at->format('d.m.Y H:i') }}</span>
@elseif ($inv = \App\Services\Invitations::status($user))
  @if ($inv['expired'])<span class="badge err">invitație expirată</span> <span class="muted">trimisă {{ $inv['sent']->format('d.m.Y') }}</span>
  @else<span class="badge warn">invitație trimisă</span> <span class="muted">valabilă până la {{ $inv['expires']->format('d.m.Y H:i') }}</span>@endif
@else
  <span class="badge warn">nu s-a autentificat</span>
@endif
