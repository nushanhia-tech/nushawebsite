(function () {
  let pending;
  window.loadNushaPdf = function () {
    if (typeof window.jspdf?.jsPDF === "function") return Promise.resolve();
    if (!pending) {
      pending = new Promise((resolve, reject) => {
        const script = document.createElement("script");
        script.src =
          "https://cdn.jsdelivr.net/npm/jspdf@4.2.1/dist/jspdf.umd.min.js";
        script.onload = () =>
          typeof window.jspdf?.jsPDF === "function"
            ? resolve()
            : reject(new Error("PDF tools did not initialize."));
        script.onerror = () => reject(new Error("PDF tools could not load."));
        document.head.append(script);
      }).catch((error) => {
        pending = undefined;
        throw error;
      });
    }
    return pending;
  };
})();
