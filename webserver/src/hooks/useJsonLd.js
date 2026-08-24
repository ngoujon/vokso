import { useEffect } from 'react';

const SCRIPT_ID = 'jsonld-structured-data';

export default function useJsonLd(data) {
  useEffect(() => {
    if (!data) {
      return undefined;
    }

    let script = document.getElementById(SCRIPT_ID);
    if (!script) {
      script = document.createElement('script');
      script.id = SCRIPT_ID;
      script.type = 'application/ld+json';
      document.head.appendChild(script);
    }
    script.textContent = JSON.stringify(data);

    return () => {
      const existing = document.getElementById(SCRIPT_ID);
      if (existing) {
        existing.remove();
      }
    };
  }, [data]);
}
