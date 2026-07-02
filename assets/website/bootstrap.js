import { startStimulusApp } from '@symfony/stimulus-bundle';

// Exported so page-specific entrypoints (e.g. forgie) can register extra controllers.
export const app = startStimulusApp();
