import Alpine from 'alpinejs';

import './echo';
import { endpointEvents } from './endpoint-events';

window.Alpine = Alpine;

Alpine.data('endpointEvents', endpointEvents);

Alpine.start();
