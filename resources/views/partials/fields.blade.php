@foreach($fields as $field)
@php
$name=$field['name'];$type=$field['type'];$value=$field['value'];$dot=trim(str_replace(['[',']'],['.',''],$name),'.');
if(isset($restore)&&$restore){$value=old($dot,$value);}
@endphp
@if($type==='hidden')<input type="hidden" name="{{ $name }}" value="{{ $value }}">
@elseif($type==='checkbox')<label class="field check"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked($value)>{{ $field['label'] }}</label>
@else<label @class(['field','wide'=>in_array($type,['textarea','multiple'])])>{{ $field['label'] }}
@if(in_array($type,['select','multiple']))<select name="{{ $name }}{{ $type==='multiple'?'[]':'' }}" @if($type==='multiple') multiple @endif @required($field['required'])>@foreach($field['options'] as $key=>$label)<option value="{{ $key }}" @selected($type==='multiple'?in_array((string)$key,array_map('strval',(array)$value)):(string)$key===(string)$value)>{{ $label }}</option>@endforeach</select>@if($type==='multiple')<small class="muted">Puedes seleccionar varias opciones con Ctrl o ⌘.</small>@endif
@elseif($type==='textarea')<textarea name="{{ $name }}" maxlength="5000" @required($field['required'])>{{ $value }}</textarea>
@else<input type="{{ $type }}" name="{{ $name }}" @if($type!=='file') value="{{ $value }}" @endif @if($type==='number') step="any" min="0" @endif @if($type==='text') maxlength="200" @endif @required($field['required'])>
@endif</label>@endif
@endforeach
