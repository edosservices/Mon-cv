<input type="hidden" name="wifi_zone_id" value="{{ $zone->id }}">
<input type="hidden" name="plan_id" value="{{ $plan->id }}">
<input type="hidden" name="mbps" value="{{ $params['mbps'] }}">
<input type="hidden" name="data_gb" value="{{ $params['data_bytes'] ? (int) round($params['data_bytes'] / 1073741824) : 0 }}">
<input type="hidden" name="username" value="{{ $identity['username'] }}">
<input type="hidden" name="password" value="{{ $identity['password'] }}">
<input type="hidden" name="draft" value="{{ $draft }}">
<input type="hidden" name="alternate_name" value="{{ $params['profile'] }}">
