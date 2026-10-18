import { cleanTitle } from './text';

test("retire les artefacts Markdown d'un titre", () => {
  expect(cleanTitle('**Les volcans**  #actifs')).toBe('Les volcans actifs');
  expect(cleanTitle(null)).toBe('');
});
