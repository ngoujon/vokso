import { useEffect } from 'react';

const SCRIPT_ID = 'jsonld-structured-data';

export default function useJsonLd(data: object | null): void {
  useEffect(() => {
    if (!data) {
      return undefined;
    }

    let script = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null;
    if (!script) {
      script = document.createElement('script');
      script.id = SCRIPT_ID;
      script.type = 'application/ld+json';
      document.head.appendChild(script);
    }
    script.textContent = JSON.stringify(data);

    return () => {
      document.getElementById(SCRIPT_ID)?.remove();
    };
  }, [data]);
}
