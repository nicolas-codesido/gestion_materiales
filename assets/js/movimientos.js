$(function () {
  // Filtro de UX: oculta destinos inválidos. La validación real está en el servidor.
  const destinosValidos = {
    LUGAR: ["PERSONA"],
    PERSONA: ["LUGAR", "PERSONA"],
  };

  function aplicarFiltro() {
    const ubicacion = $("#sel-material option:selected").data("ubicacion");
    const validos = destinosValidos[ubicacion] || [];
    const materialId = $("#sel-material").val();

    $("#sel-destino > option").each(function () {
      if ($(this).val() === "") return;
      const tipoOk = validos.includes($(this).data("tipo"));
      const ok = tipoOk;
      $(this).prop("hidden", !ok);
      if (!ok && $(this).is(":selected")) $("#sel-destino").val("");
    });
  }

  $(document).on("change", "#sel-material", aplicarFiltro);
  aplicarFiltro();
});
